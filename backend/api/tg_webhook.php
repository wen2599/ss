<?php
// backend/api/tg_webhook.php

// Include the main configuration file
require_once __DIR__ . '/config.php';

/**
 * Sends a message to a specific Telegram chat.
 *
 * @param int    $chat_id The ID of the chat to send the message to.
 * @param string $text    The message text to send.
 */
function sendMessage(int $chat_id, string $text): void {
    // Get the bot token from the config file.
    $bot_token = TELEGRAM_BOT_TOKEN;
    if (empty($bot_token) || $bot_token === 'YOUR_TELEGRAM_BOT_TOKEN') {
        // Silently fail if the bot token is not set.
        error_log("Telegram Bot Token is not configured.");
        return;
    }

    $url = "https://api.telegram.org/bot{$bot_token}/sendMessage";

    $post_fields = [
        'chat_id' => $chat_id,
        'text'    => $text,
        'parse_mode' => 'HTML' // Use HTML for formatting like <b>, <i>, etc.
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post_fields));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    if (curl_errno($ch) || $http_code !== 200) {
        error_log("Failed to send message to Telegram. HTTP Code: {$http_code}. Response: {$response}");
    }
    curl_close($ch);
}

// --- Main Webhook Logic ---

// Immediately acknowledge the request to Telegram to prevent timeouts
http_response_code(200);
echo json_encode(['status' => 'ok']);

// If running under FPM, we can close the connection to the client and continue processing
if (function_exists('fastcgi_finish_request')) {
    fastcgi_finish_request();
}

// Get the raw POST data from the webhook
$update_json = file_get_contents('php://input');
$update = json_decode($update_json, true);

// Exit if the update is not a valid message
if (!$update || !isset($update['message']['text'])) {
    // error_log("Invalid or non-text message update received.");
    exit();
}

$message = $update['message'];
$chat_id = $message['chat']['id'];
$user_id = $message['from']['id'];
$text = trim($message['text']);

// --- Security Check: Only allow the Super Admin ---
$super_admin_id = defined('TELEGRAM_SUPER_ADMIN_ID') ? TELEGRAM_SUPER_ADMIN_ID : 0;

if ($user_id != $super_admin_id) {
    sendMessage($chat_id, "<b>Permission Denied.</b> You are not authorized to use this bot.");
    error_log("Unauthorized access attempt by user ID: {$user_id}");
    exit();
}

// --- Command Processing ---
// Only process messages that look like commands
if (strpos($text, '/') === 0) {
    $parts = explode(' ', $text, 2);
    $command = $parts[0];
    $argument = trim($parts[1] ?? '');

    // Include database connection only when a command is received
    require_once __DIR__ . '/database.php';

    try {
        $pdo = getDbConnection();

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

        sendMessage($chat_id, $response_text);

    } catch (Exception $e) {
        error_log("Bot command failed: " . $e->getMessage());
        sendMessage($chat_id, "<b>Error:</b> An internal error occurred while processing your command.");
    }
}

?>
