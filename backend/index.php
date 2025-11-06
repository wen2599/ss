<?php
// backend/index.php

// A simple logger function, writes to the root directory
function write_log($message, $log_file = 'debug.log') {
    $log_entry = date('Y-m-d H:i:s') . ' - ' . $message . "\n";
    // All logs are written to the main public_html directory
    file_put_contents(__DIR__ . '/' . $log_file, $log_entry, FILE_APPEND);
}

// Get the request path
$request_uri = strtok($_SERVER['REQUEST_URI'], '?');

// --- ROUTE: Telegram Webhook ---
if ($request_uri === '/internal/telegram_webhook.php') {
    write_log("Telegram Webhook Route Hit. Method: " . $_SERVER['REQUEST_METHOD'], 'telegram.log');

    // Load dependencies ONLY for this route
    require_once __DIR__ . '/core/initialize.php';

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        exit('Method Not Allowed');
    }

    $update_json = file_get_contents('php://input');
    write_log("Raw Update: " . $update_json, 'telegram.log');

    // ... (这里是 telegram_webhook.php 的所有剩余逻辑)
    $update = json_decode($update_json, true);
    if (!isset($update['message']['chat']['id'])) { exit(); }
    $chat_id = $update['message']['chat']['id'];
    $text = $update['message']['text'] ?? '';

    $admin_id = $_ENV['TELEGRAM_ADMIN_ID'] ?? null;
    if ($chat_id != $admin_id) { exit(); }

    $reply_text = "Unknown command.";
    if (strpos($text, '/delete_user') === 0) {
        $parts = explode(' ', $text, 2);
        $email_to_delete = $parts[1] ?? '';
        if (filter_var($email_to_delete, FILTER_VALIDATE_EMAIL)) {
            try {
                $stmt = $pdo->prepare("DELETE FROM users WHERE email = ?");
                $stmt->execute([$email_to_delete]);
                $count = $stmt->rowCount();
                $reply_text = "$count user(s) deleted: {$email_to_delete}";
            } catch (PDOException $e) {
                $reply_text = "Database error.";
                write_log("DB Error: " . $e->getMessage(), 'telegram.log');
            }
        } else {
            $reply_text = "Invalid format. Use: /delete_user email@example.com";
        }
    }
    
    // Send reply
    $bot_token = $_ENV['TELEGRAM_BOT_TOKEN'];
    $url = "https://api.telegram.org/bot{$bot_token}/sendMessage";
    $params = ['chat_id' => $chat_id, 'text' => $reply_text];
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $params);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_exec($ch);
    curl_close($ch);

    write_log("Reply sent: {$reply_text}", 'telegram.log');
    exit();
}

// --- ROUTE: Public APIs ---
if (strpos($request_uri, '/api/') === 0) {
    // ... 这里的 API 路由逻辑保持不变 ...
    $endpoint = substr($request_uri, strlen('/api/'));
    switch ($endpoint) {
        case 'users/register':
        case 'users/login':
            require __DIR__ . '/api/users.php';
            break;
        // ... 其他 case ...
        case 'emails/list':
            require __DIR__ . '/api/emails.php';
            break;
        default:
            http_response_code(404);
            echo json_encode(['error' => 'API endpoint not found']);
            break;
    }
    exit();
}

// --- Fallback Route ---
http_response_code(404);
echo json_encode(['error' => 'Resource not found']);
