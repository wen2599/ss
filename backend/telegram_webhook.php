<?php
// --- Telegram Bot Webhook (Pure PHP) ---

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

// 2. 接收来自 Telegram 的原始数据
$update = file_get_contents('php://input');
$data = json_decode($update, true);

// 3. 验证并解析数据
// 我们只关心频道里的新消息 (channel_post)
if (isset($data['channel_post']['text'])) {
    
    $lottery_number = $data['channel_post']['text'];
    $chat_id = $data['channel_post']['chat']['id']; // 这就是频道 ID

    // (可选) 验证是否来自指定的管理频道 ID
    $admin_channel_id = getenv('ADMIN_CHANNEL_ID'); // 从环境变量读取
    if ($admin_channel_id && $chat_id != $admin_channel_id) {
        // 如果设置了频道 ID，但消息来源不匹配，则直接退出
        http_response_code(403);
        echo "Invalid Channel";
        exit;
    }

    // 4. 将号码转发到后端存储 API
    $ch = curl_init();

    curl_setopt($ch, CURLOPT_URL, $store_api_url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query(['number' => $lottery_number]));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    // 5. 记录日志或响应 Telegram
    if ($http_code == 200) {
        // 成功
        http_response_code(200);
        echo "Number stored successfully.";
    } else {
        // 失败
        http_response_code(500); 
        error_log("Failed to store lottery number. API response: " . $response);
        echo "Failed to store number.";
    }

} else {
    // 如果不是预期的频道消息，直接返回 OK
    http_response_code(200);
    echo "Not a channel post, ignoring.";
}

/**
 * --- 如何使用 ---
 * 1. 上传此文件到 Serv00 服务器的 public_html 目录下。
 * 2. 在 Serv00 控制面板中设置环境变量: `TELEGRAM_BOT_TOKEN` 和 (可选的) `ADMIN_CHANNEL_ID`。
 * 3. 运行一次 setWebhook 命令 (我已经帮你做了)。
 * 4. 将 Bot 添加为频道的管理员。
 */
?>
