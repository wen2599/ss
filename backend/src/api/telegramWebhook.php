<?php

// This is the new, interactive webhook handler.
// It processes commands from users and posts from the channel.

// --- Bootstrap ---
// This script is called directly by the Telegram webhook.
// It needs to load all dependencies itself because it does not run through index.php.
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/Telegram.php';

// --- Logging Utility ---
function log_to_file($message) {
    // Logs to a file in the `backend` directory.
    $log_file = dirname(__DIR__) . '/telegram_webhook.log';
    $timestamp = date('Y-m-d H:i:s');
    // Using print_r for arrays/objects, and ensuring the message is a string.
    $log_message = is_string($message) ? $message : print_r($message, true);
    file_put_contents($log_file, "[$timestamp] " . $log_message . "\n", FILE_APPEND);
}

log_to_file("--- Webhook script started ---");

// --- Configuration Validation ---
if (empty(TELEGRAM_BOT_TOKEN)) {
    log_to_file('CRITICAL: TELEGRAM_BOT_TOKEN is not defined. The bot cannot start.');
    throw new \RuntimeException('CRITICAL: TELEGRAM_BOT_TOKEN is not defined. The bot cannot start.');
}
if (empty(TELEGRAM_CHANNEL_ID)) {
    log_to_file('CRITICAL: TELEGRAM_CHANNEL_ID is not defined. The bot cannot start.');
    throw new \RuntimeException('CRITICAL: TELEGRAM_CHANNEL_ID is not defined. The bot cannot start.');
}

// --- Main Logic ---
$json = file_get_contents('php://input');
log_to_file("Raw request body: " . $json);

$update = json_decode($json, true);
log_to_file("Decoded update: " . print_r($update, true));

if (!$update) {
    log_to_file("No update or JSON decoding failed. Exiting.");
    http_response_code(200);
    exit;
}

// --- Route based on update type ---
if (isset($update['channel_post'])) {
    log_to_file("Routing to handleChannelPost.");
    handleChannelPost($update['channel_post']);
    exit;
}

if (isset($update['message'])) {
    log_to_file("Routing to handleUserMessage.");
    handleUserMessage($update['message']);
    exit;
}

log_to_file("No valid message type found in update. Exiting.");


// --- Function Definitions ---
function handleChannelPost(array $post): void {
    log_to_file("handleChannelPost received: " . print_r($post, true));

    if ($post['chat']['id'] != TELEGRAM_CHANNEL_ID) {
        log_to_file("Unauthorized channel ID: " . $post['chat']['id']);
        return;
    }

    $messageText = trim($post['text'] ?? '');
    if (empty($messageText)) {
        log_to_file("Empty message text in channel post. Doing nothing.");
        return;
    }

    $parts = preg_split('/\s+/', $messageText, 2);
    if (count($parts) === 2) {
        try {
            $conn = getDbConnection();
            $stmt = $conn->prepare("INSERT INTO lottery_numbers (issue_number, winning_numbers, drawing_date) VALUES (?, ?, ?)");
            $stmt->bind_param("sss", $parts[0], $parts[1], date('Y-m-d'));
            $stmt->execute();
            $stmt->close();
            $conn->close();
            log_to_file("Successfully saved lottery number for issue: " . $parts[0]);
        } catch (Exception $e) {
            log_to_file("DB Error in handleChannelPost: " . $e->getMessage());
        }
    } else {
        log_to_file("Message did not match expected format: " . $messageText);
    }
}

function handleUserMessage(array $message): void {
    log_to_file("handleUserMessage received: " . print_r($message, true));
    $chatId = $message['chat']['id'];
    $text = trim($message['text'] ?? '');
    log_to_file("Processing message from chat ID $chatId: '$text'");

    $keyboard = [
        'keyboard' => [[['text' => '最新开奖']]],
        'resize_keyboard' => true,
        'one_time_keyboard' => false
    ];

    switch ($text) {
        case '/start':
            $reply = "欢迎使用开奖查询机器人！\n请使用下方的菜单查询最新开奖结果。";
            log_to_file("Replying to /start command.");
            sendMessage($chatId, $reply, $keyboard);
            break;

        case '最新开奖':
            log_to_file("Handling '最新开奖' command.");
            try {
                $conn = getDbConnection();
                $sql = "SELECT issue_number, winning_numbers, drawing_date FROM lottery_numbers ORDER BY id DESC LIMIT 1";
                $result = $conn->query($sql);
                if ($result && $result->num_rows > 0) {
                    $row = $result->fetch_assoc();
                    $reply = "<b>最新开奖结果</b>\n\n" .
                             "<b>期号:</b> " . htmlspecialchars($row['issue_number']) . "\n" .
                             "<b>号码:</b> " . htmlspecialchars($row['winning_numbers']) . "\n" .
                             "<b>日期:</b> " . htmlspecialchars($row['drawing_date']);
                    log_to_file("Found latest result: " . print_r($row, true));
                } else {
                    $reply = "暂无开奖记录，请稍后再试。";
                    log_to_file("No lottery records found in DB.");
                }
                $conn->close();
            } catch (Exception $e) {
                $reply = "抱歉，查询时遇到错误，请稍后再试。";
                log_to_file("DB Error in handleUserMessage: " . $e->getMessage());
            }
            sendMessage($chatId, $reply, $keyboard);
            break;

        default:
            $reply = "无法识别的命令。请使用下方菜单中的按钮。";
            log_to_file("Unrecognized command: '$text'");
            sendMessage($chatId, $reply, $keyboard);
            break;
    }
    log_to_file("Finished processing message for chat ID $chatId.");
}