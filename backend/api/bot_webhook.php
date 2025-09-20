<?php
// backend/api/bot_webhook.php

// Include the database connection and .env loader
require_once __DIR__ . '/database.php';

// --- Basic Webhook Setup ---

// For debugging: Log all incoming requests to a file.
// In a real application, you would use a more robust logging library like Monolog.
$log_file = __DIR__ . '/webhook_debug.log';
$request_data = file_get_contents('php://input');
file_put_contents($log_file, "---[ " . date('Y-m-d H:i:s') . " ]---\n" . $request_data . "\n\n", FILE_APPEND);

// Get the JSON update from Telegram
$update = json_decode($request_data, true);

// If json_decode fails, or if there is no message, exit early.
if (!$update || !isset($update['message'])) {
    http_response_code(200); // Respond with 200 OK to Telegram, but do nothing.
    exit();
}

// Extract key information from the update
$message = $update['message'];
$chat_id = $message['chat']['id'];
$text = $message['text'] ?? ''; // Message text might not exist (e.g., for a photo)

// --- 1. Admin Verification ---
// Get the admin chat ID from environment variables.
$admin_chat_id = $_ENV['TELEGRAM_CHAT_ID'] ?? null;

// If the admin ID is not set, or if the message is not from the admin, do nothing.
// This is a silent security measure.
if (!$admin_chat_id || $chat_id != $admin_chat_id) {
    http_response_code(200);
    exit();
}

// --- 2. Command Parsing ---
// At this point, we know the message is from the authorized admin.

// We only care about the /deleteuser command for now.
if (strpos($text, '/deleteuser') === 0) {
    // Extract the email from the command, e.g., "/deleteuser user@example.com"
    $parts = explode(' ', $text, 2);
    $email_to_delete = $parts[1] ?? null;

    if (empty($email_to_delete) || !filter_var($email_to_delete, FILTER_VALIDATE_EMAIL)) {
        sendTelegramMessage($chat_id, "无效的命令。用法: /deleteuser <email>");
    } else {
        // --- 3. User Deletion Logic ---
        try {
            $db = getDbConnection();
            $stmt = $db->prepare("DELETE FROM users WHERE email = :email");
            $stmt->execute([':email' => $email_to_delete]);

            $affected_rows = $stmt->rowCount();

            if ($affected_rows > 0) {
                sendTelegramMessage($chat_id, "成功删除用户: " . $email_to_delete);
            } else {
                sendTelegramMessage($chat_id, "未找到用户: " . $email_to_delete);
            }
        } catch (Exception $e) {
            sendTelegramMessage($chat_id, "删除用户时发生数据库错误。");
            file_put_contents($log_file, "Database error during deletion: " . $e->getMessage() . "\n", FILE_APPEND);
        }
    }
}

// --- Final Response to Telegram ---
// We send a 200 OK to Telegram to acknowledge receipt of the update.
// The actual reply to the user is sent asynchronously by the sendTelegramMessage function.
http_response_code(200);
echo json_encode(['status' => 'ok']);


/**
 * Helper function to send a message to a Telegram chat.
 *
 * @param int|string $chat_id The chat ID to send the message to.
 * @param string $text The message text.
 * @return void
 */
function sendTelegramMessage($chat_id, $text) {
    $bot_token = $_ENV['TELEGRAM_BOT_TOKEN'] ?? null;
    if (!$bot_token) {
        // Log the error, as we cannot reply without a token.
        file_put_contents(__DIR__ . '/webhook_debug.log', "TELEGRAM_BOT_TOKEN is not set.\n", FILE_APPEND);
        return;
    }

    $api_url = "https://api.telegram.org/bot{$bot_token}/sendMessage";

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
    $result = file_get_contents($api_url, false, $context);

    if ($result === FALSE) {
        file_put_contents(__DIR__ . '/webhook_debug.log', "Failed to send Telegram message.\n", FILE_APPEND);
    }
}
?>
