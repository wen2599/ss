
<?php

// This is the new, interactive webhook handler.
// It processes commands from users and posts from the channel.

require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/core/Database.php';
require_once dirname(__DIR__) . '/core/Telegram.php';

// --- Configuration Validation ---
// Ensure that the necessary Telegram credentials are set in the environment.
// The bot cannot function without these, so we fail loudly if they are missing.
if (empty(TELEGRAM_BOT_TOKEN)) {
    throw new \RuntimeException('CRITICAL: TELEGRAM_BOT_TOKEN is not defined. The bot cannot start.');
}
if (empty(TELEGRAM_CHANNEL_ID)) {
    throw new \RuntimeException('CRITICAL: TELEGRAM_CHANNEL_ID is not defined. The bot cannot start.');
}

// --- Main Logic ---
// Get the raw POST data from the request
$json = file_get_contents('php://input');
// Decode the JSON data into a PHP array
$update = json_decode($json, true);

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

    // --- Keyboard Layout ---
    // Define the main keyboard with added buttons for new features.
    $keyboard = [
        'keyboard' => [
            [['text' => '🏆 最新开奖']], // Top row: Main feature
            [['text' => '📊 历史开奖'], ['text' => '❓ 使用帮助']], // Second row: Additional features
        ],
        'resize_keyboard' => true, // Make the keyboard smaller
        'one_time_keyboard' => false // Keep the keyboard visible
    ];

    switch ($text) {
        case '/start':
            $reply = "👋 欢迎使用开奖查询机器人！\n\n请从下方的菜单中选择一个操作：";
            sendMessage($chatId, $reply, $keyboard);
            break;

        case '🏆 最新开奖':
            try {
                $conn = getDbConnection();
                $sql = "SELECT issue_number, winning_numbers, drawing_date FROM lottery_numbers ORDER BY id DESC LIMIT 1";
                $result = $conn->query($sql);
                if ($result && $result->num_rows > 0) {
                    $row = $result->fetch_assoc();
                    $reply = "🎉 <b>最新开奖结果</b> 🎉\n\n" .
                             "<b>🔹 期号:</b> " . htmlspecialchars($row['issue_number']) . "\n" .
                             "<b>🔸 中奖号码:</b> " . htmlspecialchars($row['winning_numbers']) . "\n" .
                             "<b>📅 日期:</b> " . htmlspecialchars($row['drawing_date']);
                } else {
                    $reply = "📪 暂无开奖记录，请稍后再试。";
                }
                $conn->close();
            } catch (Exception $e) {
                error_log("Failed to fetch latest number for user: " . $e->getMessage());
                $reply = "⚠️ 抱歉，查询时遇到错误，请稍后再试。";
            }
            sendMessage($chatId, $reply, $keyboard);
            break;

        case '📊 历史开奖':
            try {
                $conn = getDbConnection();
                // Fetch the last 5 winning numbers
                $sql = "SELECT issue_number, winning_numbers, drawing_date FROM lottery_numbers ORDER BY id DESC LIMIT 5";
                $result = $conn->query($sql);
                if ($result && $result->num_rows > 0) {
                    $reply = "📜 <b>最近 5 期开奖历史</b>\n\n";
                    while ($row = $result->fetch_assoc()) {
                        $reply .= "<b>🔹 期号:</b> " . htmlspecialchars($row['issue_number']) . "\n" .
                                  "<b>🔸 号码:</b> " . htmlspecialchars($row['winning_numbers']) . "\n" .
                                  "<b>📅 日期:</b> " . htmlspecialchars($row['drawing_date']) . "\n" .
                                  "--------------------\n";
                    }
                } else {
                    $reply = "📪 暂无历史开奖记录。";
                }
                $conn->close();
            } catch (Exception $e) {
                error_log("Failed to fetch history for user: " . $e->getMessage());
                $reply = "⚠️ 抱歉，查询时遇到错误，请稍后再试。";
            }
            sendMessage($chatId, $reply, $keyboard);
            break;

        case '❓ 使用帮助':
            $reply = "ℹ️ <b>机器人使用帮助</b>\n\n" .
                     "这是一个简单的开奖结果查询机器人。\n\n" .
                     "🔹 点击 <b>'🏆 最新开奖'</b> - 获取最近一期的开奖结果。\n" .
                     "🔹 点击 <b>'📊 历史开奖'</b> - 查看最近 5 期的开奖历史。\n\n" .
                     "机器人会定时从指定频道自动更新开奖号码。";
            sendMessage($chatId, $reply, $keyboard);
            break;

        default:
            $reply = "🤔 无法识别的命令。请使用下方菜单中的按钮进行操作。";
            sendMessage($chatId, $reply, $keyboard);
            break;
    }
}
