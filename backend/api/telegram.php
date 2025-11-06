<?php
// No JSON headers here, Telegram expects a simple 200 OK.
require_once '../core/config.php';
require_once '../core/db.php';
require_once '../core/telegram_bot.php';

$update = json_decode(file_get_contents("php://input"), TRUE);

if (!$update) {
    exit;
}

if (isset($update["message"])) {
    $chat_id = $update["message"]["chat"]["id"];
    $text = $update["message"]["text"];

    // Security: Only respond to the admin
    if ($chat_id != $config['TELEGRAM_ADMIN_CHAT_ID']) {
        send_telegram_message($chat_id, "Sorry, you are not authorized to use this bot.");
        exit;
    }

    // Command to delete a user
    if (strpos($text, "/delete") === 0) {
        $parts = explode(" ", $text);
        if (count($parts) == 2 && filter_var($parts[1], FILTER_VALIDATE_EMAIL)) {
            $email_to_delete = $parts[1];
            
            try {
                $pdo = get_db_connection();
                $stmt = $pdo->prepare("DELETE FROM users WHERE email = ?");
                $stmt->execute([$email_to_delete]);
                
                if ($stmt->rowCount() > 0) {
                    send_telegram_message($chat_id, "User '{$email_to_delete}' has been deleted successfully.");
                } else {
                    send_telegram_message($chat_id, "User '{$email_to_delete}' not found.");
                }
            } catch (Exception $e) {
                send_telegram_message($chat_id, "Database error: " . $e->getMessage());
            }
        } else {
            send_telegram_message($chat_id, "Invalid command format. Use: /delete user@example.com");
        }
    } else {
        send_telegram_message($chat_id, "Unknown command. Available commands:\n/delete <email>");
    }
}
