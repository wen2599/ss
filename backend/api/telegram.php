<?php

// --- Diagnostic Logging ---
// This is a temporary measure to debug the unresponsive bot.
// It logs incoming requests and any errors to a file.
$log_file = __DIR__ . '/webhook_log.txt';
$raw_input = file_get_contents("php://input");
$headers = [];
foreach ($_SERVER as $key => $value) {
    if (substr($key, 0, 5) == 'HTTP_') {
        $headers[$key] = $value;
    }
}
$log_entry = "--- " . date('Y-m-d H:i:s') . " ---\n";
$log_entry .= "RAW INPUT:\n" . $raw_input . "\n";
$log_entry .= "HEADERS:\n" . print_r($headers, true) . "\n";
// --- End Diagnostic Logging ---


try {
    // No JSON headers here, Telegram expects a simple 200 OK.
    require_once '../core/config.php';
    require_once '../core/db.php';
    require_once '../core/telegram_bot.php';

    $update = json_decode($raw_input, TRUE);

    if (!$update) {
        // Still exit silently if there's no update, as this could be a health check.
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
    // Final log write before successful exit
    file_put_contents($log_file, $log_entry . "STATUS: Successfully processed.\n", FILE_APPEND);

} catch (Exception $e) {
    // --- Error Logging ---
    $error_message = "ERROR: " . $e->getMessage() . "\n";
    $error_message .= "FILE: " . $e->getFile() . "\n";
    $error_message .= "LINE: " . $e->getLine() . "\n";
    $error_message .= "TRACE:\n" . $e->getTraceAsString() . "\n";

    $log_entry .= $error_message;

    // Attempt to write the detailed error to the log file
    file_put_contents($log_file, $log_entry, FILE_APPEND);

    // Attempt to notify the admin of the error via Telegram, if possible.
    // This might fail if the config itself is broken, but it's worth a try.
    if (function_exists('send_telegram_message') && isset($config['TELEGRAM_ADMIN_CHAT_ID'])) {
        $admin_chat_id = $config['TELEGRAM_ADMIN_CHAT_ID'];
        send_telegram_message($admin_chat_id, "CRITICAL ERROR: The bot encountered an exception. Check the webhook_log.txt file for details.");
    }

    // Respond with a generic error to the user if a chat_id is available
    if (isset($chat_id)) {
         send_telegram_message($chat_id, "An internal error occurred. The administrator has been notified.");
    }

}
