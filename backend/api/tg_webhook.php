<?php
// backend/api/tg_webhook.php

error_log("--- tg_webhook.php execution started ---");

// Include the main configuration file
require_once __DIR__ . '/config.php';

/**
 * Sends a message to a specific Telegram chat.
 */
function sendMessage(int $chat_id, string $text): void {
    error_log("Attempting to send message to chat_id: {$chat_id}");
    $bot_token = TELEGRAM_BOT_TOKEN;
    if (empty($bot_token) || $bot_token === 'YOUR_TELEGRAM_BOT_TOKEN') {
        error_log("DEBUG: Telegram Bot Token is not configured. Cannot send message.");
        return;
    }

    $url = "https://api.telegram.org/bot{$bot_token}/sendMessage";
    $post_fields = ['chat_id' => $chat_id, 'text' => $text, 'parse_mode' => 'HTML'];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post_fields));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    curl_close($ch);

    if ($curl_error) {
        error_log("DEBUG: cURL error while sending message: " . $curl_error);
    } elseif ($http_code !== 200) {
        error_log("DEBUG: Failed to send message to Telegram. HTTP Code: {$http_code}. Response: {$response}");
    } else {
        error_log("DEBUG: Message sent successfully to chat_id: {$chat_id}.");
    }
}

// Immediately acknowledge the request to Telegram
http_response_code(200);
echo json_encode(['status' => 'ok']);
if (function_exists('fastcgi_finish_request')) {
    fastcgi_finish_request();
}

// Get the raw POST data from the webhook
$update_json = file_get_contents('php://input');
error_log("DEBUG: Received raw update: " . $update_json);
$update = json_decode($update_json, true);

if (!$update || !isset($update['message']['text'])) {
    error_log("DEBUG: Invalid or non-text message update received. Exiting.");
    exit();
}

$message = $update['message'];
$chat_id = $message['chat']['id'];
$user_id = $message['from']['id'];
$text = trim($message['text']);
error_log("DEBUG: Parsed message from user_id: {$user_id} in chat_id: {$chat_id}. Text: {$text}");

// Security Check
$super_admin_id = defined('TELEGRAM_SUPER_ADMIN_ID') ? TELEGRAM_SUPER_ADMIN_ID : 0;
error_log("DEBUG: Super Admin ID from config is: {$super_admin_id}");

if ($user_id != $super_admin_id) {
    error_log("DEBUG: Unauthorized access attempt by user ID: {$user_id}. Sending permission denied message.");
    sendMessage($chat_id, "<b>Permission Denied.</b> You are not authorized to use this bot.");
    exit();
}
error_log("DEBUG: User is authorized as Super Admin.");

// Command Processing
if (strpos($text, '/') === 0) {
    $parts = explode(' ', $text, 2);
    $command = $parts[0];
    $argument = trim($parts[1] ?? '');
    error_log("DEBUG: Processing command '{$command}' with argument '{$argument}'.");

    require_once __DIR__ . '/database.php';

    try {
        $pdo = getDbConnection();
        error_log("DEBUG: Database connection successful.");

        switch ($command) {
            case '/start':
                $response_text = "Welcome, Admin! Available commands:\n/listusers\n/deleteuser <email>";
                break;

            case '/listusers':
                $stmt = $pdo->query("SELECT id, email, created_at FROM users ORDER BY id ASC");
                $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
                if (empty($users)) {
                    $response_text = "No users found in the database.";
                } else {
                    $response_text = "<b>User List (" . count($users) . "):</b>\n\n";
                    foreach ($users as $user) {
                        $response_text .= "<b>ID:</b> " . htmlspecialchars($user['id']) . "\n";
                        $response_text .= "<b>Email:</b> <code>" . htmlspecialchars($user['email']) . "</code>\n";
                        $response_text .= "<b>Created:</b> " . htmlspecialchars($user['created_at']) . "\n\n";
                    }
                }
                break;

            case '/deleteuser':
                if (empty($argument) || !filter_var($argument, FILTER_VALIDATE_EMAIL)) {
                    $response_text = "Please provide a valid user email to delete.\nUsage: <code>/deleteuser user@example.com</code>";
                } else {
                    $email_to_delete = $argument;
                    $stmt = $pdo->prepare("DELETE FROM users WHERE email = :email");
                    $stmt->execute([':email' => $email_to_delete]);

                    if ($stmt->rowCount() > 0) {
                        $response_text = "✅ Successfully deleted user: <code>" . htmlspecialchars($email_to_delete) . "</code>";
                    } else {
                        $response_text = "⚠️ User not found: <code>" . htmlspecialchars($email_to_delete) . "</code>";
                    }
                }
                break;

            default:
                $response_text = "Unknown command: " . htmlspecialchars($command);
                break;
        }

        error_log("DEBUG: Preparing to send response for command '{$command}'.");
        sendMessage($chat_id, $response_text);

    } catch (Exception $e) {
        error_log("FATAL: Bot command failed with exception: " . $e->getMessage());
        sendMessage($chat_id, "<b>Error:</b> An internal error occurred while processing your command.");
    }
}
error_log("--- tg_webhook.php execution finished ---");
?>
