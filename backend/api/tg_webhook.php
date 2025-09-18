<?php
// backend/api/tg_webhook.php
// This webhook is dual-purpose:
// 1. It parses winning number announcements from a specific channel.
// 2. It handles admin commands from the superuser.

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/database.php';

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
    $lottery_data = [
        'lottery_name' => null,
        'issue_number' => null,
        'winning_numbers' => null,
    ];
    $patterns = [
        '新澳六合彩' => '/新澳门六合彩第:(\d+)期开奖结果:\s*([\d\s]+)\s*([\p{Han}\s]+)\s*([🔴🟢🔵\s]+)/u',
        '香港六合彩' => '/香港六合彩第:(\d+)期开奖结果:\s*([\d\s]+)\s*([\p{Han}\s]+)\s*([🔴🟢🔵\s]+)/u',
        '老澳21.30' => '/老澳21\.30第:(\d+)\s*期开奖结果:\s*([\d\s]+)\s*([\p{Han}\s]+)\s*([🔴🟢🔵\s]+)/u',
    ];
    foreach ($patterns as $name => $pattern) {
        if (preg_match($pattern, $text, $matches)) {
            $lottery_data['lottery_name'] = $name;
            $lottery_data['issue_number'] = $matches[1];
            $numbers = preg_split('/\s+/', trim($matches[2]), -1, PREG_SPLIT_NO_EMPTY);
            $zodiacs = preg_split('/\s+/', trim($matches[3]), -1, PREG_SPLIT_NO_EMPTY);
            $color_emojis = preg_split('/\s+/', trim($matches[4]), -1, PREG_SPLIT_NO_EMPTY);
            $color_map = ['🔴' => 'red', '🟢' => 'green', '🔵' => 'blue'];
            $colors = array_map(fn($emoji) => $color_map[$emoji] ?? 'unknown', $color_emojis);
            if (count($numbers) === 7 && count($zodiacs) === 7 && count($colors) === 7) {
                $lottery_data['winning_numbers'] = [
                    'numbers' => array_map('intval', $numbers),
                    'zodiacs' => $zodiacs,
                    'colors' => $colors,
                    'special_number' => (int)end($numbers),
                ];
            }
            break;
        }
    }
    return $lottery_data;
}

// --- Main Webhook Logic ---
$update_json = file_get_contents('php://input');
$update = json_decode($update_json, true);

if (!$update) {
    exit();
}

http_response_code(200);
echo json_encode(['status' => 'ok']);
if (function_exists('fastcgi_finish_request')) {
    fastcgi_finish_request();
}

$pdo = getDbConnection();

// --- Robustly Extract Key Information ---
$chat_id = null;
$user_id = null;
$text = null;
$is_command = false;
$is_channel_post = false;

if (isset($update['message'])) {
    $chat_id = $update['message']['chat']['id'];
    $user_id = $update['message']['from']['id'];
    $text = trim($update['message']['text'] ?? '');
    if (isset($text[0]) && $text[0] === '/') {
        $is_command = true;
    }
} else if (isset($update['channel_post'])) {
    $chat_id = $update['channel_post']['chat']['id'];
    $text = $update['channel_post']['text'] ?? '';
    $is_channel_post = true;
}

// --- Logic Execution ---

// Scenario 1: It's a command from a user
if ($is_command) {
    if ($user_id != TELEGRAM_SUPER_ADMIN_ID) {
        sendMessage($chat_id, "You are not authorized.");
        exit();
    }

    $parts = explode(' ', $text, 2);
    $command = $parts[0];
    $argument = $parts[1] ?? '';

    switch ($command) {
        case '/start':
            sendMessage($chat_id, "Welcome, Admin!");
            break;
        case '/listusers':
            $stmt = $pdo->query("SELECT id, email FROM users");
            $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $reply = "Users:\n";
            foreach($users as $user) {
                $reply .= "- {$user['email']}\n";
            }
            sendMessage($chat_id, $reply);
            break;
        case '/deleteuser':
             // ... logic to delete user ...
             break;
        default:
            sendMessage($chat_id, "Unknown command.");
            break;
    }

// Scenario 2: It's a post from the lottery channel
} else if ($is_channel_post && $chat_id == TELEGRAM_CHANNEL_ID) {
    $result = parse_lottery_result($text);
    if ($result['lottery_name']) {
        try {
            $sql = "INSERT INTO lottery_draws (lottery_name, issue_number, winning_numbers) VALUES (:name, :issue, :numbers) ON DUPLICATE KEY UPDATE winning_numbers=VALUES(winning_numbers)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':name' => $result['lottery_name'],
                ':issue' => $result['issue_number'],
                ':numbers' => json_encode($result['winning_numbers']),
            ]);

            $settlement_context = [
                'pdo' => $pdo,
                'issue_number' => $result['issue_number'],
                'winning_numbers' => $result['winning_numbers'],
            ];
            include __DIR__ . '/settle_bets.php';
        } catch (Exception $e) {
            file_put_contents('tg_webhook_error.log', "Error: " . $e->getMessage() . "\n", FILE_APPEND);
        }
    }
}
?>
