<?php
// backend/api/tg_webhook.php

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/error_logger.php';

// --- Main Logic Handler Functions ---

/**
 * Handles incoming admin commands.
 * @param string $command The command to execute (e.g., '/listusers').
 * @param string $argument The argument provided with the command.
 * @param PDO $pdo The database connection object.
 * @param int $chat_id The chat ID to send the response to.
 */
function handle_admin_command(string $command, string $argument, PDO $pdo, int $chat_id) {
    switch ($command) {
        case '/start':
            sendMessage($chat_id, "Welcome, Admin! Available commands: /listusers, /deleteuser [email]");
            break;
        case '/listusers':
            $stmt = $pdo->query("SELECT id, email, created_at FROM users ORDER BY created_at DESC");
            $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $reply = "<b>Registered Users:</b>\n";
            if (empty($users)) {
                $reply .= "No users found.";
            } else {
                foreach ($users as $user) {
                    $reply .= "- <code>" . htmlspecialchars($user['email']) . "</code> (ID: " . $user['id'] . ")\n";
                }
            }
            sendMessage($chat_id, $reply);
            break;
        case '/deleteuser':
            // Basic implementation for user deletion
            if (empty($argument)) {
                sendMessage($chat_id, "Please provide an email to delete. Usage: /deleteuser user@example.com");
                return;
            }
            $stmt = $pdo->prepare("DELETE FROM users WHERE email = ?");
            $stmt->execute([$argument]);
            if ($stmt->rowCount() > 0) {
                sendMessage($chat_id, "User '" . htmlspecialchars($argument) . "' has been deleted.");
            } else {
                sendMessage($chat_id, "User '" . htmlspecialchars($argument) . "' not found.");
            }
            break;
        default:
            sendMessage($chat_id, "Unknown command.");
            break;
    }
}

/**
 * Handles incoming lottery result posts from the channel.
 * @param string $text The text content of the channel post.
 * @param PDO $pdo The database connection object.
 */
function handle_lottery_result(string $text, PDO $pdo) {
    $result = parse_lottery_result($text);
    if ($result['lottery_name'] && $result['issue_number'] && $result['winning_numbers']) {
        $sql = "INSERT INTO lottery_draws (lottery_name, issue_number, winning_numbers) VALUES (:name, :issue, :numbers) ON DUPLICATE KEY UPDATE winning_numbers=VALUES(winning_numbers)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':name' => $result['lottery_name'],
            ':issue' => $result['issue_number'],
            ':numbers' => json_encode($result['winning_numbers'])
        ]);

        // After saving the draw, trigger the settlement process
        $settlement_context = [
            'pdo' => $pdo,
            'issue_number' => $result['issue_number'],
            'winning_numbers' => $result['winning_numbers']
        ];
        include __DIR__ . '/settle_bets.php'; // This will now use the context variables
    }
}

// --- Helper Functions ---
function sendMessage(int $chat_id, string $text) {
    $url = "https://api.telegram.org/bot" . TELEGRAM_BOT_TOKEN . "/sendMessage";
    $post_fields = ['chat_id' => $chat_id, 'text' => $text, 'parse_mode' => 'HTML'];
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_HTTPHEADER, ["Content-Type:multipart/form-data"]);
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $post_fields);
    @curl_exec($ch);
    if (curl_errno($ch)) {
        log_error('Curl error in sendMessage: ' . curl_error($ch));
    }
    curl_close($ch);
}

function parse_lottery_result(string $text) {
    $lottery_data = ['lottery_name' => null, 'issue_number' => null, 'winning_numbers' => null];
    $patterns = [
        '新澳六合彩' => '/新澳门六合彩第:(\d+)期开奖结果:\s*([\d\s]+)\s*([\p{Han}\s]+)\s*([🔴🟢🔵\s]+)/u',
        '香港六合彩' => '/香港六合彩第:(\d+)奖结果:\s*([\d\s]+)\s*([\p{Han}\s]+)\s*([🔴🟢🔵\s]+)/u',
        '老澳21.30' => '/老澳21第:(\d+)\s*期开奖结果:\s*([\d\s]+)\s*([\p{Han}\s]+)\s*([🔴🟢🔵\s]+)/u',
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
                $lottery_data['winning_numbers'] = ['numbers' => array_map('intval', $numbers), 'zodiacs' => $zodiacs, 'colors' => $colors, 'special_number' => (int)end($numbers)];
            }
            break;
        }
    }
    return $lottery_data;
}

// --- Main Execution Block ---
try {
    $update_json = file_get_contents('php://input');
    if ($update_json === false) {
        throw new Exception("Failed to read php://input stream.");
    }

    $update = json_decode($update_json, true);
    if (!$update) {
        http_response_code(200);
        echo json_encode(['status' => 'ok', 'message' => 'No update data received.']);
        exit();
    }

    http_response_code(200);
    echo json_encode(['status' => 'ok']);
    if (function_exists('fastcgi_finish_request')) {
        fastcgi_finish_request();
    }

    $pdo = getDbConnection();

    if (isset($update['message']) && isset($update['message']['text']) && $update['message']['text'][0] === '/') {
        $chat_id = $update['message']['chat']['id'];
        $user_id = $update['message']['from']['id'];
        $text = trim($update['message']['text']);

        if ($user_id == TELEGRAM_SUPER_ADMIN_ID) {
            $parts = explode(' ', $text, 2);
            handle_admin_command($parts[0], $parts[1] ?? '', $pdo, $chat_id);
        } else {
            sendMessage($chat_id, "You are not authorized to perform this action.");
        }

    } else if (isset($update['channel_post']) && $update['channel_post']['chat']['id'] == TELEGRAM_CHANNEL_ID) {
        $text = $update['channel_post']['text'] ?? '';
        handle_lottery_result($text, $pdo);
    }

} catch (Exception $e) {
    log_error("tg_webhook.php error: " . $e->getMessage() . " in " . $e->getFile() . " on line " . $e->getLine());
}
?>
