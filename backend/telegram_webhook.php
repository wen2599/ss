<?php
// --- Telegram Bot Webhook (Pure PHP) ---

// 1. 设置环境变量 (未来请在 Serv00 面板中配置)
// 为了安全，不要将 Token 硬编码在代码里
$bot_token = getenv('TELEGRAM_BOT_TOKEN');
if (!$bot_token) {
    // 如果没有设置环境变量，为了开发方便，可以临时在这里设置
    // 但强烈建议部署后删除此行，改用环境变量
    $bot_token = 'YOUR_TELEGRAM_BOT_TOKEN'; 
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
    // (可选) 如果存储 API 需要验证，可以在这里添加 Header
    // curl_setopt($ch, CURLOPT_HTTPHEADER, array('Authorization: Bearer YOUR_API_KEY'));

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    // 5. 记录日志或响应 Telegram
    if ($http_code == 200) {
        // 成功，可以向 Telegram 返回一个 200 OK
        http_response_code(200);
        echo "Number stored successfully.";
    } else {
        // 失败，记录错误，并通知 Telegram 服务器处理失败
        // Telegram 会在一段时间后重试
        http_response_code(500); 
        error_log("Failed to store lottery number. API response: " . $response);
        echo "Failed to store number.";
    }

} else {
    // 如果不是预期的频道消息，直接返回 OK，避免 Telegram 重试
    http_response_code(200);
    echo "Not a channel post, ignoring.";
}

/**
 * --- 如何使用 ---
 * 
 * 1. 将此文件 `telegram_webhook.php` 上传到您的 Serv00 服务器，例如放在 `public_html/` 目录下。
 *    假设可以通过 `https://wenge.cloudns.ch/telegram_webhook.php` 访问到它。
 * 
 * 2. 获取您的 Bot Token:
 *    - 在 Telegram 中与 @BotFather 对话。
 *    - 创建一个新的 Bot，您会得到一个类似 `123456:ABC-DEF1234ghIkl-zyx57W2v1u123ew11` 的 Token。
 * 
 * 3. 设置 Webhook:
 *    - 在您的浏览器中访问以下 URL (替换 YOUR_BOT_TOKEN 和 YOUR_WEBHOOK_URL):
 *      https://api.telegram.org/bot<YOUR_BOT_TOKEN>/setWebhook?url=<YOUR_WEBHOOK_URL>
 *      例如:
 *      https://api.telegram.org/bot123456:ABC-DEF1234ghIkl-zyx57W2v1u123ew11/setWebhook?url=https://wenge.cloudns.ch/telegram_webhook.php
 * 
 * 4. (推荐) 在 Serv00 控制面板中设置环境变量:
 *    - `TELEGRAM_BOT_TOKEN`: 你的机器人 Token。
 *    - `ADMIN_CHANNEL_ID`: 你的管理频道的 ID (是一个以 `-100` 开头的数字)。
 * 
 * 5. 将您的 Bot 添加为频道的管理员。
 *    现在，您在频道里发送的任何消息，都会被自动发送到这个脚本进行处理。
 */
?>
