<?php

// ==================================================================
// ==                      SINGLE FILE FIX                         ==
// ==================================================================
// This file consolidates all backend logic into a single entry point
// to eliminate any potential server-side include/path issues.
// ==================================================================


// --- Global Error & Exception Handling ---
ini_set('display_errors', 0);
error_reporting(E_ALL);

set_exception_handler(function ($exception) {
    error_log("Global Exception: " . $exception->getMessage());
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => 'Internal Server Error', 'message' => $exception->getMessage()]);
    exit;
});

set_error_handler(function ($severity, $message, $file, $line) {
    if (!(error_reporting() & $severity)) { return; }
    throw new ErrorException($message, 0, $severity, $file, $line);
});


// --- Core Class: Response ---
class Response {
    public static function json($data, $statusCode = 200) {
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=utf-8');
        header('Access-Control-Allow-Origin: *');
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        exit;
    }
}


// --- Core Class: DotEnv ---
class DotEnv {
    protected $path;
    public function __construct(string $path) {
        if (!file_exists($path)) { throw new \InvalidArgumentException(sprintf('File does not exist at path: %s', $path)); }
        $this->path = $path;
    }
    public function getVariables() :array {
        if (!is_readable($this->path)) { throw new \RuntimeException(sprintf('File is not readable at path: %s', $this->path)); }
        $variables = [];
        $lines = file($this->path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            if (strpos(trim($line), '#') === 0) { continue; }
            if (strpos($line, '=') === false) { continue; }
            list($name, $value) = explode('=', $line, 2);
            $variables[trim($name)] = trim($value);
        }
        return $variables;
    }
}


// --- Core Function: getDbConnection ---
function getDbConnection(): mysqli {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASSWORD, DB_DATABASE, (int)DB_PORT);
    if ($conn->connect_error) {
        throw new Exception("Database Connection Failed: " . $conn->connect_error);
    }
    if (!$conn->set_charset("utf8mb4")) {
        error_log("Error loading character set utf8mb4: " . $conn->error);
    }
    return $conn;
}

// --- Core Function: sendMessage (Telegram) ---
function sendMessage(int $chatId, string $text, ?array $keyboard = null): void {
    $url = "https://api.telegram.org/bot" . TELEGRAM_BOT_TOKEN . "/sendMessage";
    $postData = ['chat_id' => $chatId, 'text' => $text, 'parse_mode' => 'HTML'];
    if ($keyboard) {
        $postData['reply_markup'] = json_encode($keyboard);
    }
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    $response = curl_exec($ch);
    if (curl_errno($ch)) {
        error_log("Telegram sendMessage cURL Error: " . curl_error($ch));
    }
    curl_close($ch);
}


// --- Configuration Loading ---
$env = [];
// This path is now definitive based on the successful debug script.
$dotenvPath = $_SERVER['DOCUMENT_ROOT'] . '/.env';
if (file_exists($dotenvPath)) {
    $dotenv = new DotEnv($dotenvPath);
    $env = $dotenv->getVariables();
} else {
    throw new \RuntimeException("CRITICAL: .env file not found at expected absolute path: {$dotenvPath}.");
}

// --- Define Constants ---
define('DB_HOST', $env['DB_HOST'] ?? null);
define('DB_PORT', $env['DB_PORT'] ?? 3306);
define('DB_DATABASE', $env['DB_DATABASE'] ?? null);
define('DB_USER', $env['DB_USER'] ?? null);
define('DB_PASSWORD', $env['DB_PASSWORD'] ?? null);
define('TELEGRAM_BOT_TOKEN', $env['TELEGRAM_BOT_TOKEN'] ?? null);
define('TELEGRAM_WEBHOOK_SECRET', $env['TELEGRAM_WEBHOOK_SECRET'] ?? null);
define('TELEGRAM_CHANNEL_ID', $env['TELEGRAM_CHANNEL_ID'] ?? null);


// --- Main Router & Logic ---
$endpoint = $_GET['endpoint'] ?? null;
$update = json_decode(file_get_contents('php://input'), true);

if ($endpoint === 'telegramWebhook') {
    $secretHeader = $_SERVER['HTTP_X_TELEGRAM_BOT_API_SECRET_TOKEN'] ?? '';
    if (!TELEGRAM_WEBHOOK_SECRET || TELEGRAM_WEBHOOK_SECRET !== $secretHeader) {
        http_response_code(401);
        error_log('Unauthorized webhook attempt.');
        exit;
    }

    if (isset($update['channel_post'])) {
        $post = $update['channel_post'];
        if ($post['chat']['id'] != TELEGRAM_CHANNEL_ID) { exit; }
        $parts = preg_split('/\s+/', trim($post['text'] ?? ''), 2);
        if (count($parts) === 2) {
            $conn = getDbConnection();
            $stmt = $conn->prepare("INSERT INTO lottery_numbers (issue_number, winning_numbers, drawing_date) VALUES (?, ?, ?)");
            $stmt->bind_param("sss", $parts[0], $parts[1], date('Y-m-d'));
            $stmt->execute();
            $conn->close();
        }
    } elseif (isset($update['message'])) {
        $message = $update['message'];
        $chatId = $message['chat']['id'];
        $text = trim($message['text'] ?? '');
        $keyboard = ['keyboard' => [[['text' => '最新开奖']]], 'resize_keyboard' => true];

        if ($text === '/start') {
            sendMessage($chatId, "欢迎！请使用菜单查询。", $keyboard);
        } elseif ($text === '最新开奖') {
            $conn = getDbConnection();
            $result = $conn->query("SELECT issue_number, winning_numbers, drawing_date FROM lottery_numbers ORDER BY id DESC LIMIT 1");
            if ($result && $result->num_rows > 0) {
                $row = $result->fetch_assoc();
                $reply = "<b>期号:</b> " . htmlspecialchars($row['issue_number']) . "\n" .
                         "<b>号码:</b> " . htmlspecialchars($row['winning_numbers']);
            } else {
                $reply = "暂无开奖记录。";
            }
            $conn->close();
            sendMessage($chatId, $reply, $keyboard);
        } else {
            sendMessage($chatId, "无法识别的命令。", $keyboard);
        }
    }
    // Respond to Telegram to acknowledge receipt of the webhook
    http_response_code(200);
    echo json_encode(['status' => 'ok']);
    exit;

} elseif ($endpoint === 'getLotteryNumber') {
    $conn = getDbConnection();
    $result = $conn->query("SELECT issue_number, winning_numbers, drawing_date, created_at FROM lottery_numbers ORDER BY id DESC LIMIT 1");
    if ($result && $result->num_rows > 0) {
        Response::json($result->fetch_assoc());
    } else {
        Response::json(['winning_numbers' => '等待开奖'], 404);
    }
    $conn->close();
} else {
    Response::json(['error' => 'Endpoint not found'], 404);
}