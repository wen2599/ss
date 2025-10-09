<?php
// backend/endpoints/telegram_webhook.php

require_once dirname(__DIR__) . '/bootstrap.php';
require_once dirname(__DIR__) . '/lib/telegram_utils.php';

// Get the raw POST data from Telegram
$content = file_get_contents("php://input");
$update = json_decode($content, true);

if (!$update) {
    exit();
}

// Process channel posts only
if (isset($update['channel_post'])) {
    $message = $update['channel_post'];
    $chat_id = $message['chat']['id'];
    $text = $message['text'];
    $user_id = $message['from']['id'];

    // Security check: only the admin can interact
    if ($user_id != TELEGRAM_ADMIN_ID) {
        send_telegram_message($chat_id, "Unauthorized user: " . $user_id);
        exit();
    }

    // Handle commands
    if (strpos($text, '/') === 0) {
        $parts = explode(' ', $text, 2);
        $command = $parts[0];
        $args = $parts[1] ?? '';

        switch ($command) {
            case '/add_result':
                $parts = explode(':', $args, 2);
                if (count($parts) !== 2) {
                    send_telegram_message($chat_id, "❗️ Invalid format. Please use: <code>/add_result &lt;Lottery Type&gt;:&lt;Numbers&gt;</code>");
                    break;
                }

                $lottery_type = trim($parts[0]);
                $numbers = trim($parts[1]);

                if (empty($lottery_type) || empty($numbers)) {
                    send_telegram_message($chat_id, "❗️ Invalid format. Both lottery type and numbers are required.");
                    break;
                }

                try {
                    $pdo = get_db_connection();
                    $stmt = $pdo->prepare("INSERT INTO lottery_results (lottery_type, numbers) VALUES (?, ?)");
                    $stmt->execute([$lottery_type, $numbers]);
                    send_telegram_message($chat_id, "✅ Successfully added results for " . $lottery_type);
                } catch (\PDOException $e) {
                    send_telegram_message($chat_id, "❌ Error saving results: " . $e->getMessage());
                }
                break;

            case '/help':
                $help_text = "<b>Available Commands:</b>\n\n";
                $help_text .= "<b>/add_result &lt;Lottery Type&gt;:&lt;Numbers&gt;</b>\n";
                $help_text .= "Adds a new lottery result.\n";
                $help_text .= "Example: <code>/add_result 大乐透:1,2,3,4,5,6</code>\n\n";
                $help_text .= "<b>/help</b>\n";
                $help_text .= "Shows this help message.";
                send_telegram_message($chat_id, $help_text);
                break;

            default:
                send_telegram_message($chat_id, "❓ Unknown command. Type /help to see all available commands.");
                break;
        }
    } else {
        // Guide user to use commands for non-command messages
        send_telegram_message($chat_id, "ℹ️ Please use a command starting with /. Type /help for a list of commands.");
    }
}

?>