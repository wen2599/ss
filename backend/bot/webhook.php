<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../config/database.php';

$botToken = getenv('TELEGRAM_BOT_TOKEN');
$adminId = getenv('TELEGRAM_ADMIN_ID');
$internalApiSecret = getenv('INTERNAL_API_SECRET');
$backendUrl = getenv('BACKEND_URL');

function sendMessage($chatId, $text) {
    global $botToken;
    $url = "https://api.telegram.org/bot{$botToken}/sendMessage";
    $postFields = ['chat_id' => $chatId, 'text' => $text];
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $postFields);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_exec($ch);
    curl_close($ch);
}

$update = json_decode(file_get_contents('php://input'), true);
if (!$update) { exit(); }

$message = $update['message'] ?? null;
$chatId = $message['chat']['id'] ?? null;
$text = $message['text'] ?? null;
$userId = $message['from']['id'] ?? null;

// 仅允许管理员操作
if ($userId != $adminId) {
    sendMessage($chatId, "抱歉，您无权操作。");
    exit;
}

// 示例命令: /add 2023123 01,02,03,04,05,06,07
if (strpos($text, '/add') === 0) {
    $parts = explode(' ', $text);
    if (count($parts) < 3) {
        sendMessage($chatId, "格式错误。示例: /add 2023123 01,02,03,04,05,06,07");
        exit;
    }
    
    $issueNumber = $parts[1];
    $numbers = $parts[2];
    
    // 调用后端 API 添加号码
    $apiUrl = $backendUrl . '/api/winning-numbers';
    $postData = json_encode(['issue_number' => $issueNumber, 'numbers' => $numbers, 'draw_date' => date('Y-m-d')]);

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $apiUrl);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $internalApiSecret
    ]);

    $response = curl_exec($ch);
    curl_close($ch);
    
    $responseData = json_decode($response, true);
    if (isset($responseData['message'])) {
        sendMessage($chatId, "成功添加开奖号码: \n期号: {$issueNumber}\n号码: {$numbers}");
    } else {
        sendMessage($chatId, "添加失败: " . ($responseData['error'] ?? $response));
    }
} 
// 注册 webhook 的命令 (一次性)
// 访问 /bot/webhook.php?setup=true 来设置
elseif (isset($_GET['setup']) && $_GET['setup'] === 'true'){
    $webhookUrl = $backendUrl . '/bot/webhook.php';
    $url = "https://api.telegram.org/bot{$botToken}/setWebhook?url=" . urlencode($webhookUrl);
    $response = file_get_contents($url);
    echo $response;
} else {
    sendMessage($chatId, "未知命令。");
}
