<?php

// This is the new, interactive webhook handler.
// It processes commands from users and posts from the channel.

// --- Security Check ---
// Ensure this script is loaded by index.php, not accessed directly.
if (!defined('DB_HOST')) {
    die('Direct access not permitted');
}

// --- Main Logic ---
$update = $GLOBALS['requestBody'] ?? null;
if (!$update) {
    // If no update, do nothing. Telegram might send empty requests to check the hook.
    exit;
}

// --- Route based on update type ---

// 1. Handle posts from the lottery channel
if (isset($update['channel_post'])) {
    handleChannelPost($update['channel_post']);
    exit;
}

// 2. Handle direct messages from users
if (isset($update['message'])) {
    handleUserMessage($update['message']);
    exit;
}


// --- Function Definitions ---

/**
 * Handles incoming posts from the designated channel.
 * @param array $post The channel_post data from the Telegram update.
 */
function handleChannelPost(array $post): void {
    // Security: Check if the post is from the allowed channel
    if ($post['chat']['id'] != TELEGRAM_CHANNEL_ID) {
        error_log("Ignoring post from unauthorized channel: " . $post['chat']['id']);
        return;
    }

    $messageText = trim($post['text'] ?? '');
    if (empty($messageText)) {
        return;
    }

    // Expected format: "issue_number winning_numbers"
    $parts = preg_split('/\s+/', $messageText, 2);
    if (count($parts) === 2) {
        try {
            $conn = getDbConnection();
            $stmt = $conn->prepare("INSERT INTO lottery_numbers (issue_number, winning_numbers, drawing_date) VALUES (?, ?, ?)");
            $stmt->bind_param("sss", $parts[0], $parts[1], date('Y-m-d'));
            $stmt->execute();
            $stmt->close();
            $conn->close();
            error_log("Successfully saved lottery number for issue: " . $parts[0]);
        } catch (Exception $e) {
            error_log("Failed to save lottery number: " . $e->getMessage());
        }
    }
}

/**
 * Handles incoming direct messages from users.
 * @param array $message The message data from the Telegram update.
 */
function handleUserMessage(array $message): void {
    $chatId = $message['chat']['id'];
    $text = trim($message['text'] ?? '');

    $keyboard = [
        'keyboard' => [
            [['text' => '最新开奖']],
            // You can add more buttons here, e.g., [['text' => '历史记录'], ['text' => '帮助']]
        ],
        'resize_keyboard' => true,
        'one_time_keyboard' => false
    ];

    switch ($text) {
        case '/start':
            $reply = "欢迎使用开奖查询机器人！\n请使用下方的菜单查询最新开奖结果。";
            sendMessage($chatId, $reply, $keyboard);
            break;

        case '最新开奖':
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
                } else {
                    $reply = "暂无开奖记录，请稍后再试。";
                }
                $conn->close();
            } catch (Exception $e) {
                error_log("Failed to fetch latest number for user: " . $e->getMessage());
                $reply = "抱歉，查询时遇到错误，请稍后再试。";
            }
            sendMessage($chatId, $reply, $keyboard);
            break;

        default:
            $reply = "无法识别的命令。请使用下方菜单中的按钮。";
            sendMessage($chatId, $reply, $keyboard);
            break;
    }
}