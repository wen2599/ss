<?php
// --- Start of Inlined Initialization Logic ---

// --- Environment Variable Loading ---
function load_env($path) {
    if (!file_exists($path)) { return; } // Silently fail if .env is not found
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        list($name, $value) = explode('=', $line, 2);
        $_ENV[trim($name)] = trim($value);
    }
}
load_env(__DIR__ . '/.env');

// --- Database Connection ---
$pdo = null;
if (class_exists('PDO')) {
    try {
        $host = $_ENV['DB_HOST'] ?? null;
        $dbname = $_ENV['DB_NAME'] ?? null;
        $user = $_ENV['DB_USER'] ?? null;
        $pass = $_ENV['DB_PASS'] ?? '';
        if ($host && $dbname && $user) {
            $dsn = "mysql:host=$host;dbname=$dbname;charset=utf8mb4";
            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ];
            $pdo = new PDO($dsn, $user, $pass, $options);
        }
    } catch (PDOException $e) {
        // Fail silently. The command handler below will check for a valid $pdo object.
    }
}

// --- End of Inlined Initialization Logic ---


// --- Main Webhook Logic ---

// --- Helper function to send a message back to Telegram ---
function sendTelegramMessage($chat_id, $text) {
    $token = $_ENV['TELEGRAM_BOT_TOKEN'] ?? null;
    if (!$token) { return; } // Cannot send message if token is not set
    $url = "https://api.telegram.org/bot{$token}/sendMessage";
    $data = [
        'chat_id' => $chat_id,
        'text' => $text,
    ];
    $options = [
        'http' => [
            'header'  => "Content-type: application/x-www-form-urlencoded\r\n",
            'method'  => 'POST',
            'content' => http_build_query($data),
        ],
    ];
    $context  = stream_context_create($options);
    @file_get_contents($url, false, $context); // Use @ to suppress errors if the API call fails
}

// Get the update from Telegram
$update = json_decode(file_get_contents('php://input'), true);

if (!$update || !isset($update['message']['text']) || !isset($update['message']['chat']['id'])) {
    exit();
}

$chat_id = $update['message']['chat']['id'];
$message_text = $update['message']['text'];
$admin_chat_id = $_ENV['TELEGRAM_ADMIN_CHAT_ID'] ?? null;

// --- Security Check: Only allow the admin to execute commands ---
if (!$admin_chat_id || (string)$chat_id !== (string)$admin_chat_id) {
    sendTelegramMessage($chat_id, "You are not authorized to use this bot.");
    exit();
}

// --- Command Parsing: /deleteuser <username> ---
if (preg_match('/^\/deleteuser\s+([a-zA-Z0-9_.-]+)$/', $message_text, $matches)) {
    // Check for DB connection
    if (!$pdo) {
        sendTelegramMessage($chat_id, "Database connection is not configured or failed.");
        exit();
    }

    $username_to_delete = $matches[1];

    try {
        $stmt = $pdo->prepare("SELECT id, is_admin FROM users WHERE username = ?");
        $stmt->execute([$username_to_delete]);
        $user = $stmt->fetch();

        if (!$user) {
            sendTelegramMessage($chat_id, "Error: User '{$username_to_delete}' not found.");
            exit();
        }

        if ($user['is_admin']) {
            sendTelegramMessage($chat_id, "Error: Cannot delete an admin account.");
            exit();
        }

        $delete_stmt = $pdo->prepare("DELETE FROM users WHERE username = ?");
        $delete_stmt->execute([$username_to_delete]);

        if ($delete_stmt->rowCount() > 0) {
            sendTelegramMessage($chat_id, "Success: User '{$username_to_delete}' has been deleted.");
        } else {
            sendTelegramMessage($chat_id, "Error: Failed to delete user '{$username_to_delete}'.");
        }

    } catch (PDOException $e) {
        sendTelegramMessage($chat_id, "A database error occurred.");
    }

} else {
    sendTelegramMessage($chat_id, "Unknown command. Available commands:\n/deleteuser <username>");
}
?>