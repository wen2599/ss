<?php
// ============== Unified Telegram Webhook Script ==============
// This single file contains all necessary functions to run the bot.
// It is designed to be deployed as a single artifact to avoid `require_once` path issues.

// --- Core Includes ---

// Load application configuration (defines constants and loads .env)
require_once __DIR__ . '/config.php';

// Set up logging and error handlers
require_once __DIR__ . '/error_logger.php';
register_error_handlers();

// Include database connection function
require_once __DIR__ . '/database.php';

// Include the bet parser
require_once __DIR__ . '/parser.php';

// Include the settlement logic
require_once __DIR__ . '/settle_bets.php';


// 7. Telegram API Communication
function sendMessage($chatId, $text, $keyboard = null) {
    $url = 'https://api.telegram.org/bot' . TELEGRAM_BOT_TOKEN . '/sendMessage';
    $post_fields = [
        'chat_id' => $chatId,
        'text' => $text,
        'parse_mode' => 'Markdown',
    ];
    if ($keyboard) {
        $post_fields['reply_markup'] = json_encode($keyboard);
    }
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_HTTPHEADER, array("Content-Type: application/json"));
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($post_fields));
    $output = curl_exec($ch);
    curl_close($ch);
    return $output;
}

function answerCallbackQuery($callbackQueryId, $text = null) {
    $url = 'https://api.telegram.org/bot' . TELEGRAM_BOT_TOKEN . '/answerCallbackQuery';
    $post_fields = ['callback_query_id' => $callbackQueryId];
    if ($text) {
        $post_fields['text'] = $text;
        $post_fields['show_alert'] = true;
    }
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_HTTPHEADER, array("Content-Type: application/json"));
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($post_fields));
    curl_exec($ch);
    curl_close($ch);
}

// --- Main Webhook Execution ---

$update = json_decode(file_get_contents('php://input'), true);

if (!$update) {
    exit();
}

// Initialize PDO connection
$pdo = getDbConnection();

// Handle callback queries (from inline keyboards)
if (isset($update['callback_query'])) {
    $callbackQuery = $update['callback_query'];
    $callbackId = $callbackQuery['id'];
    $chatId = $callbackQuery['message']['chat']['id'];
    $data = $callbackQuery['data'];

    if ($chatId != TELEGRAM_SUPER_ADMIN_ID) {
        answerCallbackQuery($callbackId, "您无权操作。");
        exit();
    }

    $message = "这是一个回调功能";
    switch($data) {
        case 'parse_latest':
            $message = "您点击了“解析最新投注”按钮。请直接发送需要解析的投注文本。";
            break;
        case 'settle_bets':
            $message = "请回复 `/settle [期号]` 来结算指定期数的投注。\n例如: `/settle 2025101`";
            break;
        case 'manual_draw':
            $message = "请回复 `/draw [名称] [号码]` 来手动录入开奖结果。\n例如: `/draw 新澳门六合彩 1,2,3,4,5,6,7`";
            break;
    }
    answerCallbackQuery($callbackId); // Acknowledge the tap
    sendMessage($chatId, $message); // Send a follow-up message
    exit();
}

// Standard message handling
$message = $update['message'] ?? null;
$chatId = $message['chat']['id'] ?? null;
$text = $message['text'] ?? '';
$userId = $message['from']['id'] ?? null;

// Only respond to the super admin
if ($userId != TELEGRAM_SUPER_ADMIN_ID) {
    sendMessage($chatId, "抱歉，您无权使用此机器人。");
    exit();
}

if (strpos($text, '/start') === 0) {
    $keyboard = [
        'inline_keyboard' => [
            [
                ['text' => '解析最新投注', 'callback_data' => 'parse_latest'],
                ['text' => '结算投注', 'callback_data' => 'settle_bets']
            ],
            [
                ['text' => '手动开奖', 'callback_data' => 'manual_draw']
            ]
        ]
    ];
    sendMessage($chatId, "欢迎使用投注管理机器人。请选择一个操作：", $keyboard);

} elseif (strpos($text, '/parse') === 0) {
    $parts = explode(' ', $text, 3);
    if (count($parts) < 3) {
        sendMessage($chatId, "格式错误。用法: `/parse [期号] [投注内容]`");
        exit();
    }
    $issueNumber = $parts[1];
    $betContent = $parts[2];

    $parsedBets = parseBets($betContent, $pdo);

    if (empty($parsedBets)) {
        sendMessage($chatId, "解析失败：无法从您的文本中识别任何投注。");
        exit();
    }

    // Save to database
    $stmt = $pdo->prepare("INSERT INTO bets (issue_number, original_content, parsed_data, status) VALUES (?, ?, ?, 'pending')");
    $stmt->execute([$issueNumber, $betContent, json_encode($parsedBets)]);

    sendMessage($chatId, "投注解析成功并已保存！\n期号: `$issueNumber`\n共解析出 `" . count($parsedBets) . "` 条投注。");

} elseif (strpos($text, '/settle') === 0) {
    $parts = explode(' ', $text, 2);
    if (count($parts) < 2 || !is_numeric($parts[1])) {
        sendMessage($chatId, "格式错误。用法: `/settle [期号]`");
        exit();
    }
    $issueNumber = trim($parts[1]);

    // Show a "processing" message
    sendMessage($chatId, "正在结算期号 `{$issueNumber}`，请稍候...");

    $result_message = settleBetsForIssue($pdo, $issueNumber);
    sendMessage($chatId, $result_message);

} elseif (strpos($text, '/draw') === 0) {
    // Corrected format: /draw [issue_number] [lottery_name] [numbers]
    $parts = explode(' ', $text, 4);
    if (count($parts) < 4) {
        sendMessage($chatId, "格式错误。用法: `/draw [期号] [开奖名称] [号码,用逗号隔开]`\n例如: `/draw 2025101 新澳门六合彩 1,2,3,4,5,6,7`");
        exit();
    }
    $issueNumber = trim($parts[1]);
    $lotteryName = trim($parts[2]);
    $numbersStr = trim($parts[3]);
    $numbers = array_map('trim', explode(',', $numbersStr));

    if (!is_numeric($issueNumber)) {
        sendMessage($chatId, "期号错误：期号必须是数字。");
        exit();
    }

    if (count($numbers) < 7) {
        sendMessage($chatId, "号码错误：必须提供至少7个号码。");
        exit();
    }

    // The last number is the special number
    $special_number = array_pop($numbers);
    // The rest are the main numbers
    $main_numbers = $numbers;

    try {
        // Corrected INSERT statement to match the new `lottery_draws` schema
        $winning_numbers_json = json_encode([
            'numbers' => $main_numbers,
            'special_number' => $special_number
        ]);

        $stmt = $pdo->prepare(
            "INSERT INTO lottery_draws (issue_number, lottery_name, winning_numbers, created_at)
             VALUES (?, ?, ?, NOW())"
        );
        $stmt->execute([$issueNumber, $lotteryName, $winning_numbers_json]);

        sendMessage($chatId, "开奖结果已手动保存！\n期号: `$issueNumber`\n名称: `$lotteryName`\n号码: `" . implode(', ', $main_numbers) . "`\n特别号: `$special_number`");

        // Automatically trigger settlement for this new draw
        $settle_message = settleBetsForIssue($pdo, $issueNumber);
        sendMessage($chatId, $settle_message);

    } catch (Exception $e) {
        // Check for duplicate entry error (error code 23000)
        if ($e->getCode() == '23000') {
             sendMessage($chatId, "保存开奖结果时发生错误：该期号 (`$issueNumber`) 的开奖结果已存在。");
        } else {
            log_error("Manual draw error: " . $e->getMessage());
            sendMessage($chatId, "保存开奖结果时发生未知错误。");
        }
    }

} else {
    // Default behavior for any other text: assume it's a bet to be parsed
    // For simplicity, we require an issue number to be set somehow.
    // Let's assume for now it must be on the first line.
    $lines = explode("\n", $text, 2);
    $issueNumber = trim($lines[0]);
    $betContent = $lines[1] ?? '';

    if (!is_numeric($issueNumber) || empty($betContent)) {
        sendMessage($chatId, "自动解析失败。请确保第一行为期号，后面为投注内容。或使用 /start 命令。");
        exit();
    }

    $parsedBets = parseBets($betContent, $pdo);

    if (empty($parsedBets)) {
        sendMessage($chatId, "解析失败：无法从您的文本中识别任何投注。");
        exit();
    }

    // Save to database
    $stmt = $pdo->prepare("INSERT INTO bets (issue_number, original_content, parsed_data, status) VALUES (?, ?, ?, 'pending')");
    $stmt->execute([$issueNumber, $betContent, json_encode($parsedBets)]);

    sendMessage($chatId, "投注自动解析成功并已保存！\n期号: `$issueNumber`\n共解析出 `" . count($parsedBets) . "` 条投注。");
}

?>
