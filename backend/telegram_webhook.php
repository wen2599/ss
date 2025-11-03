<?php
// --- Telegram Bot Webhook (Pure PHP) ---

// Include the environment variable loader
require_once __DIR__ . '/utils/config_loader.php';

// 1. 获取机器人令牌
// 为了安全，令牌应该作为环境变量 'TELEGRAM_BOT_TOKEN' 在服务器上设置。
$bot_token = getenv('TELEGRAM_BOT_TOKEN');

// 如果未设置环境变量，则脚本无法工作，直接退出并记录错误。
if (!$bot_token) {
    http_response_code(500);
    error_log("TELEGRAM_BOT_TOKEN environment variable not set.");
    exit("Configuration error: Bot token not set.");
}

// 后端存储开奖号码的 API 地址
$store_api_url = 'https://wenge.cloudns.ch/api/store_lottery_number.php';

/**
 * Sends a message to a specific Telegram chat.
 *
 * @param int|string $chat_id The ID of the chat to send the message to.
 * @param string $text The message text.
 * @param string $bot_token The Telegram bot token.
 * @return bool|string The response from the Telegram API, or false on failure.
 */
function sendMessage($chat_id, $text, $bot_token) {
    $url = "https://api.telegram.org/bot{$bot_token}/sendMessage";
    $post_fields = [
        'chat_id' => $chat_id,
        'text' => $text,
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post_fields));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($ch);
    curl_close($ch);
    return $response;
}

// 2. 接收来自 Telegram 的原始数据
$update = file_get_contents('php://input');
$data = json_decode($update, true);

// 3. 验证并解析数据

// --- A. Handle Private Messages ---
if (isset($data['message'])) {
    $chat_id = $data['message']['chat']['id'];
    $text = $data['message']['text'];

    // Respond to the /start command
    if ($text === '/start') {
        $welcome_message = "你好！欢迎使用机器人。";
        sendMessage($chat_id, $welcome_message, $bot_token);
        http_response_code(200);
        echo "Welcome message sent.";
        exit;
    }

    // You can add more private message commands here...

    // If it's a private message but not a command we recognize, just exit quietly.
    http_response_code(200);
    echo "Private message received, no action taken.";
    exit;

// --- B. Handle Channel Posts ---
} elseif (isset($data['channel_post']['text'])) {

    $text = $data['channel_post']['text'];
    $chat_id = $data['channel_post']['chat']['id']; // This is the channel ID

    // (Optional) Validate if it's from the specified admin channel ID
    $admin_channel_id = getenv('ADMIN_CHANNEL_ID'); // Read from environment variables
    if ($admin_channel_id && $chat_id != $admin_channel_id) {
        http_response_code(403);
        echo "Invalid Channel";
        exit;
    }

    // --- Store the entire message text ---
    $post_data = [
        'number' => $text, // Send the full text of the message
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $store_api_url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post_data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    // --- TEMPORARY DEBUGGING ---
    $debug_message = "Attempting to store lottery number.\n";
    $debug_message .= "API URL: " . $store_api_url . "\n";
    $debug_message .= "HTTP Code: " . $http_code . "\n";
    $debug_message .= "API Response: " . $response . "\n";
    $admin_id = getenv('TELEGRAM_ADMIN_ID');
    if ($admin_id) {
        sendMessage($admin_id, $debug_message, $bot_token);
    }
    // --- END TEMPORARY DEBUGGING ---

    if ($http_code == 201) { // Check for 201 Created
        http_response_code(200);
        echo "Lottery data stored successfully.";
    } else {
        http_response_code(500);
        error_log("Failed to store lottery data. API response: " . $response);
        echo "Failed to store data.";
    }

} else {
    // If it's not a message type we handle, just return OK
    http_response_code(200);
    echo "Webhook received, but no actionable data found.";
}

/**
 * --- 如何使用 ---
 * 1. 上传此文件到 Serv00 服务器的 public_html 目录下。
 * 2. 在 Serv00 控制面板中设置环境变量: `TELEGRAM_BOT_TOKEN` 和 (可选的) `ADMIN_CHANNEL_ID`。
 * 3. 运行一次 setWebhook 命令 (我已经帮你做了)。
 * 4. 将 Bot 添加为频道的管理员。
 */
?>
