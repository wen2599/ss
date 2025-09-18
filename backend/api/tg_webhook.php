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
            $keyboard = [
                'inline_keyboard' => [
                    [
                        ['text' => '列出所有用户', 'callback_data' => '/listusers']
                    ]
                    // Future buttons can be added here
                ]
            ];
            sendMessage($chat_id, "欢迎您，管理员！请选择一个操作：", $keyboard);
            break;
        case '/listusers':
            $stmt = $pdo->query("SELECT id, email, created_at FROM users ORDER BY created_at DESC");
            $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $reply = "<b>已注册用户:</b>\n";
            if (empty($users)) {
                $reply .= "没有找到用户。";
            } else {
                foreach ($users as $user) {
                    $reply .= "- <code>" . htmlspecialchars($user['email']) . "</code> (ID: " . $user['id'] . ")\n";
                }
            }
            sendMessage($chat_id, $reply);
            break;
        case '/deleteuser':
            if (empty($argument)) {
                sendMessage($chat_id, "请输入需要删除的邮箱。用法：/deleteuser user@example.com");
                return;
            }
            $stmt = $pdo->prepare("DELETE FROM users WHERE email = ?");
            $stmt->execute([$argument]);
            if ($stmt->rowCount() > 0) {
                sendMessage($chat_id, "用户 '" . htmlspecialchars($argument) . "' 已被删除。");
            } else {
                sendMessage($chat_id, "未找到用户 '" . htmlspecialchars($argument) . "'。");
            }
            break;
        default:
            sendMessage($chat_id, "未知命令。");
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

        $settlement_context = [
            'pdo' => $pdo,
            'issue_number' => $result['issue_number'],
            'winning_numbers' => $result['winning_numbers']
        ];
        include __DIR__ . '/settle_bets.php';
    }
}

// --- Helper Functions ---
function sendMessage(int $chat_id, string $text, ?array $reply_markup = null) {
    $url = "https://api.telegram.org/bot" . TELEGRAM_BOT_TOKEN . "/sendMessage";
    $post_fields = ['chat_id' => $chat_id, 'text' => $text, 'parse_mode' => 'HTML'];
    if ($reply_markup) {
        $post_fields['reply_markup'] = $reply_markup;
    }
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_HTTPHEADER, ["Content-Type: application/json"]);
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($post_fields));
    @curl_exec($ch);
    if (curl_errno($ch)) {
        log_error('Curl error in sendMessage: ' . curl_error($ch));
    }
    curl_close($ch);
}

function answerCallbackQuery(string $callback_query_id, ?string $text = null) {
    $url = "https://api.telegram.org/bot" . TELEGRAM_BOT_TOKEN . "/answerCallbackQuery";
    $post_fields = ['callback_query_id' => $callback_query_id];
    if ($text) {
        $post_fields['text'] = $text;
    }
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_HTTPHEADER, ["Content-Type: application/json"]);
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($post_fields));
    @curl_exec($ch);
    curl_close($ch);
}

function parse_lottery_result(string $text) {
    $lottery_data = ['lottery_name' => null, 'issue_number' => null, 'winning_numbers' => null];
    $patterns = [
        '新澳六合彩' => '/新澳门六合彩第:(\d+)期开奖结果:\s*([\d\s]+)\s*([\p{Han}\s]+)\s*([🔴🟢🔵\s]+)/u',
        '香港六合彩' => '/香港六合彩第:(\d+)奖结果:\s*([\d\s]+)\s*([\p{Han}\s]+)\s*([🔴🟢🔵\s]+)/u',
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
            $colors = array_map(function($emoji) use ($color_map) {
                return $color_map[$emoji] ?? 'unknown';
            }, $color_emojis);
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
            sendMessage($chat_id, "您没有权限执行此操作。");
        }

    } else if (isset($update['callback_query'])) {
        $callback_query = $update['callback_query'];
        $user_id = $callback_query['from']['id'];
        $chat_id = $callback_query['message']['chat']['id'];
        $command = $callback_query['data'];

        answerCallbackQuery($callback_query['id']);

        if ($user_id == TELEGRAM_SUPER_ADMIN_ID) {
            handle_admin_command($command, '', $pdo, $chat_id);
        } else {
            answerCallbackQuery($callback_query['id'], "您没有权限执行此操作。");
        }

    } else if (isset($update['channel_post']) && $update['channel_post']['chat']['id'] == TELEGRAM_CHANNEL_ID) {
        $text = $update['channel_post']['text'] ?? '';
        handle_lottery_result($text, $pdo);
    }

} catch (Exception $e) {
    log_error("tg_webhook.php error: " . $e->getMessage() . " in " . $e->getFile() . " on line " . $e->getLine());
}
?>
