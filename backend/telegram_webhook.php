<?php
// --- Telegram Bot Webhook (Pure PHP) ---

// Include the environment variable loader
require_once __DIR__ . '/utils/config_loader.php';

// --- Security Check: Verify Webhook Secret Token ---
$secret_token = getenv('TELEGRAM_WEBHOOK_SECRET');
if ($secret_token) {
    $header_token = $_SERVER['HTTP_X_TELEGRAM_BOT_API_SECRET_TOKEN'] ?? null;
    if ($header_token !== $secret_token) {
        // This is a security risk. Log it and deny the request.
        error_log("Webhook secret token mismatch.");
        http_response_code(403);
        exit('Forbidden');
    }
}
// --- End Security Check ---

// 1. 获取机器人令牌
// 为了安全，令牌应该作为环境变量 'TELEGRAM_BOT_TOKEN' 在服务器上设置。
$bot_token = getenv('TELEGRAM_BOT_TOKEN');

// 如果未设置环境变量，则脚本无法工作，直接退出并记录错误。
if (!$bot_token) {
    error_log("TELEGRAM_BOT_TOKEN environment variable not set.");
    send_json_response(false, "Configuration error: Bot token not set.", 500);
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

/**
 * Sends a consistent JSON response and exits the script.
 *
 * @param bool $success Whether the operation was successful.
 * @param string $message A descriptive message.
 * @param int $code The HTTP response code to send.
 */
function send_json_response($success, $message, $code) {
    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode(['success' => $success, 'message' => $message]);
    exit;
}

/**
 * Parses the lottery result text from a channel post.
 *
 * @param string $text The text of the channel post.
 * @return array|false An associative array with parsed data or false on failure.
 */
function parseLotteryText($text) {
    // Example format: "双色球 - 第 2024001 期: 01 02 03 04 05 06 + 07"
    // The regex is designed to be flexible with different lottery names and number formats.
    $pattern = '/^(?<lottery_type>.+?)\s*-\s*第\s*(?<issue_number>\d+)\s*期:\s*(?<numbers>.+)$/u';

    if (preg_match($pattern, $text, $matches)) {
        return [
            'lottery_type' => trim($matches['lottery_type']),
            'issue_number' => trim($matches['issue_number']),
            'numbers'      => trim($matches['numbers']),
        ];
    }

    return false;
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
        send_json_response(true, "Welcome message sent.", 200);
    }

    // You can add more private message commands here...

    // If it's a private message but not a command we recognize, just exit quietly.
    send_json_response(true, "Private message received, no action taken.", 200);

// --- B. Handle Channel Posts ---
} elseif (isset($data['channel_post']['text'])) {

    $text = $data['channel_post']['text'];
    $chat_id = $data['channel_post']['chat']['id']; // This is the channel ID

    // (Optional) Validate if it's from the specified admin channel ID
    $admin_channel_id = getenv('ADMIN_CHANNEL_ID'); // Read from environment variables
    if ($admin_channel_id && $chat_id != $admin_channel_id) {
        send_json_response(false, "Message from unauthorized channel.", 403);
    }

    // --- Parse the lottery data from the message text ---
    $parsed_data = parseLotteryText($text);

    if (!$parsed_data) {
        // If parsing fails, log it but don't stop the webhook.
        // This allows other bots or systems to process the message.
        error_log("Failed to parse lottery text: " . $text);
        send_json_response(true, "Message received, but format not recognized.", 200);
    }

    // --- Send structured data to the storage API ---
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $store_api_url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($parsed_data)); // Send parsed data
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    // --- Verify API Response ---
    if ($http_code == 201) {
        send_json_response(true, "Lottery data stored successfully.", 200);
    } else {
        // Log the error for debugging
        $error_message = sprintf(
            "Failed to store lottery data. API Status: %d, Response: %s, Original Text: '%s'",
            $http_code,
            $response,
            $text
        );
        error_log($error_message);

        // Send a debug message to the admin if configured
        $admin_id = getenv('TELEGRAM_ADMIN_ID');
        if ($admin_id) {
            sendMessage($admin_id, $error_message, $bot_token);
        }

        send_json_response(false, "Failed to store data due to an internal error.", 500);
    }

} else {
    // If it's not a message type we handle, just return OK
    send_json_response(true, "Webhook received, but no actionable data found.", 200);
}

/**
 * --- 如何使用 ---
 * 1. 上传此文件到 Serv00 服务器的 public_html 目录下。
 * 2. 在 Serv00 控制面板中设置环境变量: `TELEGRAM_BOT_TOKEN` 和 (可选的) `ADMIN_CHANNEL_ID`。
 * 3. 运行一次 setWebhook 命令 (我已经帮你做了)。
 * 4. 将 Bot 添加为频道的管理员。
 */
?>
