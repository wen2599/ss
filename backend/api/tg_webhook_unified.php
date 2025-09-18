<?php
// ============== Unified Telegram Webhook Script ==============
// This single file contains all necessary functions to run the bot.
// It is designed to be deployed as a single artifact to avoid `require_once` path issues.

// --- Core Configuration & Setup ---

// 1. Environment Variable Loader (from env_loader.php)
function loadEnv($path) {
    if (!file_exists($path)) {
        http_response_code(500);
        error_log("FATAL: .env file not found at " . $path);
        exit("FATAL: .env file not found.");
    }
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        list($name, $value) = explode('=', $line, 2);
        $name = trim($name);
        $value = trim($value);
        if (!array_key_exists($name, $_SERVER) && !array_key_exists($name, $_ENV)) {
            putenv(sprintf('%s=%s', $name, $value));
            $_ENV[$name] = $value;
            $_SERVER[$name] = $value;
        }
    }
}
// Load the .env file from the same directory as this script
loadEnv(__DIR__ . '/../.env');


// 2. Application Configuration (from config.php)
define('DB_HOST', getenv('DB_HOST') ?: '127.0.0.1');
define('DB_PORT', getenv('DB_PORT') ?: '3306');
define('DB_DATABASE', getenv('DB_DATABASE') ?: null);
define('DB_USERNAME', getenv('DB_USERNAME') ?: null);
define('DB_PASSWORD', getenv('DB_PASSWORD') ?: null);
define('TELEGRAM_BOT_TOKEN', getenv('TELEGRAM_BOT_TOKEN') ?: null);
define('TELEGRAM_CHAT_ID', getenv('TELEGRAM_CHAT_ID') ?: null);
define('TELEGRAM_SUPER_ADMIN_ID', 1878794912); // System constant

// 3. Error Logger (from error_logger.php)
function log_error($message) {
    $log_file = __DIR__ . '/../logs/error.log';
    $log_dir = dirname($log_file);
    if (!is_dir($log_dir)) {
        mkdir($log_dir, 0755, true);
    }
    $timestamp = date("Y-m-d H:i:s");
    file_put_contents($log_file, "[$timestamp] " . $message . "\n", FILE_APPEND);
}

// Set global error/exception handlers
set_error_handler(function($severity, $message, $file, $line) {
    if (!(error_reporting() & $severity)) { return; }
    log_error("Error: [$severity] $message in $file on line $line");
    return true; // Don't execute PHP internal error handler
});
set_exception_handler(function($exception) {
    log_error("Exception: " . $exception->getMessage() . " in " . $exception->getFile() . " on line " . $exception->getLine());
});


// --- Database ---

// 4. Database Connection (from database.php)
function getDbConnection() {
    $dsn = "mysql:host=".DB_HOST.";port=".DB_PORT.";dbname=".DB_DATABASE.";charset=utf8mb4";
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];
    try {
        return new PDO($dsn, DB_USERNAME, DB_PASSWORD, $options);
    } catch (\PDOException $e) {
        log_error('Database Connection Error: ' . $e->getMessage());
        // Do not echo errors back to Telegram
        exit();
    }
}


// --- Core Bot Logic ---

// 5. Bet Parser (from parser.php)
function parseBets(string $inputText, $pdo): array {
    $stmt = $pdo->query("SELECT rule_key, rule_value FROM lottery_rules");
    $rules_raw = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $rules = [];
    foreach ($rules_raw as $row) {
        $rules[$row['rule_key']] = json_decode($row['rule_value'], true);
    }
    $zodiac_mappings = $rules['zodiac_mappings'] ?? [];
    $color_mappings = $rules['color_mappings'] ?? [];

    $bets = [];
    $remainingText = trim($inputText);
    $patterns = [
        'special' => '/^特(\d+)[\sx*](\d+)/u',
        'zodiac' => '/^((?:[\p{Han}](?!波))+?)各数(\d+)/u',
        'color' => '/^([\p{Han}]+波)各(\d+)/u',
    ];

    while (strlen($remainingText) > 0) {
        $matchFound = false;
        foreach ($patterns as $type => $pattern) {
            if (preg_match($pattern, $remainingText, $matches)) {
                $matchFound = true;
                $fullMatch = $matches[0];
                switch ($type) {
                    case 'special':
                        $bets[] = ['type' => 'special', 'number' => $matches[1], 'amount' => (int)$matches[2], 'display_name' => '特码'];
                        break;
                    case 'zodiac':
                        $items_str = $matches[1];
                        $amount = (int)$matches[2];
                        $items = preg_split('//u', $items_str, -1, PREG_SPLIT_NO_EMPTY);
                        foreach ($items as $item) {
                            if (isset($zodiac_mappings[$item])) {
                                $bets[] = ['type' => 'zodiac', 'name' => $item, 'numbers' => $zodiac_mappings[$item], 'amount' => $amount, 'display_name' => '生肖'];
                            }
                        }
                        break;
                    case 'color':
                        $item = $matches[1];
                        $amount = (int)$matches[2];
                        if (isset($color_mappings[$item])) {
                            $bets[] = ['type' => 'color', 'name' => $item, 'numbers' => $color_mappings[$item], 'amount' => $amount, 'display_name' => '波色'];
                        }
                        break;
                }
                $remainingText = ltrim(substr($remainingText, strlen($fullMatch)));
                break;
            }
        }
        if (!$matchFound) {
            log_error("Unparseable text remaining: " . $remainingText);
            break;
        }
    }
    return $bets;
}

// 6. Telegram API Communication
function sendMessage($chatId, $text, $keyboard = null) {
    $url = 'https://api.telegram.org/bot' . TELEGRAM_BOT_TOKEN . '/sendMessage';
    $post_fields = [
        'chat_id' => $chatId,
        'text' => $text,
        'parse_mode' => 'Markdown',
    ];
    if ($keyboard) {
        $post_fields['reply_markup'] = json_encode($keyboard);
    }
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_HTTPHEADER, array("Content-Type: application/json"));
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($post_fields));
    $output = curl_exec($ch);
    curl_close($ch);
    return $output;
}

function answerCallbackQuery($callbackQueryId, $text = null) {
    $url = 'https://api.telegram.org/bot' . TELEGRAM_BOT_TOKEN . '/answerCallbackQuery';
    $post_fields = ['callback_query_id' => $callbackQueryId];
    if ($text) {
        $post_fields['text'] = $text;
        $post_fields['show_alert'] = true;
    }
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_HTTPHEADER, array("Content-Type: application/json"));
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($post_fields));
    curl_exec($ch);
    curl_close($ch);
}

// --- Main Webhook Execution ---

$update = json_decode(file_get_contents('php://input'), true);

if (!$update) {
    exit();
}

// Initialize PDO connection
$pdo = getDbConnection();

// Handle callback queries (from inline keyboards)
if (isset($update['callback_query'])) {
    $callbackQuery = $update['callback_query'];
    $callbackId = $callbackQuery['id'];
    $chatId = $callbackQuery['message']['chat']['id'];
    $data = $callbackQuery['data'];

    if ($chatId != TELEGRAM_SUPER_ADMIN_ID) {
        answerCallbackQuery($callbackId, "您无权操作。");
        exit();
    }

    $message = "这是一个回调功能";
    switch($data) {
        case 'parse_latest':
            $message = "您点击了“解析最新投注”按钮。请直接发送需要解析的投注文本。";
            break;
        case 'settle_bets':
            $message = "您点击了“结算投注”按钮。此功能待实现。";
            break;
        case 'manual_draw':
            $message = "您点击了“手动开奖”按钮。此功能待实现。";
            break;
    }
    answerCallbackQuery($callbackId); // Acknowledge the tap
    sendMessage($chatId, $message); // Send a follow-up message
    exit();
}

// Standard message handling
$message = $update['message'] ?? null;
$chatId = $message['chat']['id'] ?? null;
$text = $message['text'] ?? '';
$userId = $message['from']['id'] ?? null;

// Only respond to the super admin
if ($userId != TELEGRAM_SUPER_ADMIN_ID) {
    sendMessage($chatId, "抱歉，您无权使用此机器人。");
    exit();
}

if (strpos($text, '/start') === 0) {
    $keyboard = [
        'inline_keyboard' => [
            [
                ['text' => '解析最新投注', 'callback_data' => 'parse_latest'],
                ['text' => '结算投注', 'callback_data' => 'settle_bets']
            ],
            [
                ['text' => '手动开奖', 'callback_data' => 'manual_draw']
            ]
        ]
    ];
    sendMessage($chatId, "欢迎使用投注管理机器人。请选择一个操作：", $keyboard);

} elseif (strpos($text, '/parse') === 0) {
    $parts = explode(' ', $text, 3);
    if (count($parts) < 3) {
        sendMessage($chatId, "格式错误。用法: `/parse [期号] [投注内容]`");
        exit();
    }
    $issueNumber = $parts[1];
    $betContent = $parts[2];

    $parsedBets = parseBets($betContent, $pdo);

    if (empty($parsedBets)) {
        sendMessage($chatId, "解析失败：无法从您的文本中识别任何投注。");
        exit();
    }

    // Save to database
    $stmt = $pdo->prepare("INSERT INTO bets (issue_number, original_content, parsed_data, status) VALUES (?, ?, ?, 'pending')");
    $stmt->execute([$issueNumber, $betContent, json_encode($parsedBets)]);

    sendMessage($chatId, "投注解析成功并已保存！\n期号: `$issueNumber`\n共解析出 `" . count($parsedBets) . "` 条投注。");

} else {
    // Default behavior for any other text: assume it's a bet to be parsed
    // For simplicity, we require an issue number to be set somehow.
    // Let's assume for now it must be on the first line.
    $lines = explode("\n", $text, 2);
    $issueNumber = trim($lines[0]);
    $betContent = $lines[1] ?? '';

    if (!is_numeric($issueNumber) || empty($betContent)) {
        sendMessage($chatId, "自动解析失败。请确保第一行为期号，后面为投注内容。或使用 /start 命令。");
        exit();
    }

    $parsedBets = parseBets($betContent, $pdo);

    if (empty($parsedBets)) {
        sendMessage($chatId, "解析失败：无法从您的文本中识别任何投注。");
        exit();
    }

    // Save to database
    $stmt = $pdo->prepare("INSERT INTO bets (issue_number, original_content, parsed_data, status) VALUES (?, ?, ?, 'pending')");
    $stmt->execute([$issueNumber, $betContent, json_encode($parsedBets)]);

    sendMessage($chatId, "投注自动解析成功并已保存！\n期号: `$issueNumber`\n共解析出 `" . count($parsedBets) . "` 条投注。");
}

?>
