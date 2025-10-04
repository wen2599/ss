<?php
// This webhook handles commands sent from a Telegram bot.
require_once __DIR__ . '/init.php';

// --- Helper function to send a message back to Telegram ---
function sendTelegramMessage($chat_id, $text) {
    $token = $_ENV['TELEGRAM_BOT_TOKEN'];
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
    file_get_contents($url, false, $context);
}

// --- Main Webhook Logic ---

// Get the update from Telegram
$update = json_decode(file_get_contents('php://input'), true);

if (!$update) {
    // No update data received
    exit();
}

// Check if it's a message and has text
if (!isset($update['message']['text']) || !isset($update['message']['chat']['id'])) {
    exit();
}

$chat_id = $update['message']['chat']['id'];
$message_text = $update['message']['text'];
$admin_chat_id = $_ENV['TELEGRAM_ADMIN_CHAT_ID'];

// --- Security Check: Only allow the admin to execute commands ---
if ((string)$chat_id !== (string)$admin_chat_id) {
    sendTelegramMessage($chat_id, "You are not authorized to use this bot.");
    exit();
}

// --- Command Parsing: /deleteuser <username> ---
if (preg_match('/^\/deleteuser\s+([a-zA-Z0-9_.-]+)$/', $message_text, $matches)) {
    $username_to_delete = $matches[1];

    try {
        // Find the user first to prevent SQL injection and check existence
        $stmt = $pdo->prepare("SELECT id, is_admin FROM users WHERE username = ?");
        $stmt->execute([$username_to_delete]);
        $user = $stmt->fetch();

        if (!$user) {
            sendTelegramMessage($chat_id, "Error: User '{$username_to_delete}' not found.");
            exit();
        }

        // Prevent admin from deleting themselves
        if ($user['is_admin']) {
            sendTelegramMessage($chat_id, "Error: Cannot delete an admin account.");
            exit();
        }

        // Perform the deletion
        $delete_stmt = $pdo->prepare("DELETE FROM users WHERE username = ?");
        $delete_stmt->execute([$username_to_delete]);

        if ($delete_stmt->rowCount() > 0) {
            sendTelegramMessage($chat_id, "Success: User '{$username_to_delete}' has been deleted.");
        } else {
            sendTelegramMessage($chat_id, "Error: Failed to delete user '{$username_to_delete}'. It might have been deleted already.");
        }

    } catch (PDOException $e) {
        // In a real app, log this error
        sendTelegramMessage($chat_id, "A database error occurred. Please check the server logs.");
    }

} else {
    sendTelegramMessage($chat_id, "Unknown command. Available commands:\n/deleteuser <username>");
}
?>