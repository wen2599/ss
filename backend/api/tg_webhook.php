<?php
// backend/api/tg_webhook.php
// This webhook is dual-purpose:
// 1. It parses winning number announcements from a specific channel.
// 2. It handles admin commands from the superuser.

require_once __DIR__ . '/database.php';
require_once __DIR__ . '/config.php';

// --- Helper Functions ---

function sendMessage($chat_id, $text) {
    $url = "https://api.telegram.org/bot" . TELEGRAM_BOT_TOKEN . "/sendMessage";
    $post_fields = ['chat_id' => $chat_id, 'text' => $text, 'parse_mode' => 'HTML'];
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_HTTPHEADER, ["Content-Type:multipart/form-data"]);
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $post_fields);
    curl_exec($ch);
    curl_close($ch);
}

function parse_lottery_result($text) {
    // [omitted - same as before]
}

// --- Main Webhook Logic ---
$update_json = file_get_contents('php://input');
$update = json_decode($update_json, true);

if (!$update) {
    exit();
}

$pdo = getDbConnection();

// --- Scenario 1: Admin Command ---
if (isset($update['message']['text']) && $update['message']['text'][0] === '/') {
    $message = $update['message'];
    $chat_id = $message['chat']['id'];
    $user_id = $message['from']['id'];
    $text = $message['text'];

    if ($user_id != TELEGRAM_SUPER_ADMIN_ID) {
        sendMessage($chat_id, "You are not authorized to use this bot.");
        exit();
    }

    $parts = explode(' ', $text);
    $command = $parts[0];

    switch ($command) {
        case '/start':
            $reply = "Welcome, Admin! Available commands:\n/listusers\n/deleteuser <email>";
            sendMessage($chat_id, $reply);
            break;

        case '/listusers':
            $stmt = $pdo->query("SELECT id, email, created_at FROM users ORDER BY created_at DESC");
            $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $reply = "<b>Registered Users:</b>\n\n";
            if (empty($users)) {
                $reply .= "No users found.";
            } else {
                foreach ($users as $user) {
                    $reply .= "<b>ID:</b> {$user['id']}, <b>Email:</b> {$user['email']}\n";
                }
            }
            sendMessage($chat_id, $reply);
            break;

        case '/deleteuser':
            if (isset($parts[1]) && filter_var($parts[1], FILTER_VALIDATE_EMAIL)) {
                $email_to_delete = $parts[1];
                $stmt = $pdo->prepare("DELETE FROM users WHERE email = :email");
                $stmt->execute([':email' => $email_to_delete]);
                if ($stmt->rowCount() > 0) {
                    sendMessage($chat_id, "User '{$email_to_delete}' has been deleted.");
                } else {
                    sendMessage($chat_id, "User '{$email_to_delete}' not found.");
                }
            } else {
                sendMessage($chat_id, "Usage: /deleteuser <email>");
            }
            break;

        default:
            sendMessage($chat_id, "Unknown command.");
            break;
    }

// --- Scenario 2: Lottery Result from Channel ---
} else if (isset($update['channel_post']['chat']['id']) && $update['channel_post']['chat']['id'] == TELEGRAM_CHANNEL_ID) {
    $message_text = $update['channel_post']['text'] ?? '';

    $result = parse_lottery_result($message_text); // Function needs to be defined as before

    if ($result['lottery_name'] && $result['issue_number'] && $result['winning_numbers']) {
        try {
            $sql = "INSERT INTO lottery_draws (lottery_name, issue_number, winning_numbers) VALUES (:lottery_name, :issue_number, :winning_numbers)
                    ON DUPLICATE KEY UPDATE winning_numbers = VALUES(winning_numbers)";

            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':lottery_name' => $result['lottery_name'],
                ':issue_number' => $result['issue_number'],
                ':winning_numbers' => json_encode($result['winning_numbers']),
            ]);

            $settlement_context = [
                'pdo' => $pdo,
                'lottery_name' => $result['lottery_name'],
                'issue_number' => $result['issue_number'],
                'winning_numbers' => $result['winning_numbers'],
            ];

            include __DIR__ . '/settle_bets.php';

        } catch (Exception $e) {
            file_put_contents('tg_webhook_error.log', "Error: " . $e->getMessage() . "\n", FILE_APPEND);
        }
    }
}

// Respond to Telegram to acknowledge receipt
http_response_code(200);
echo json_encode(['status' => 'ok']);
?>
