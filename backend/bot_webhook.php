<?php
require_once __DIR__ . '/utils/config_loader.php';
require_once __DIR__ . '/config/database.php';


$botToken = getenv('BOT_TOKEN');
$adminId = getenv('ADMIN_TELEGRAM_ID');

$secretToken = getenv('TELEGRAM_WEBHOOK_SECRET');

// --- 安全性检查 ---
// 仅在非 CLI 环境下检查 Secret Token
if (php_sapi_name() !== 'cli') {
    $headerToken = $_SERVER['HTTP_X_TELEGRAM_BOT_API_SECRET_TOKEN'] ?? null;
    if ($headerToken !== $secretToken) {
        http_response_code(403);
        // 记录可疑访问
        error_log("Webhook security check failed. IP: {$_SERVER['REMOTE_ADDR']}");
        exit('Forbidden');
    }
}

$content = file_get_contents("php://input");
$update = json_decode($content, true);

// 基本的更新验证
if (!$update || !isset($update['update_id'])) {
    http_response_code(400); // Bad Request
    exit('Invalid update received');
}

if (isset($update['message']['from']['id']) && $update['message']['from']['id'] == $adminId) {
    
    $message = $update['message'];
    $text = $message['text'];

    if (preg_match('/^开奖\s+(\d{4}-\d{2}-\d{2})\s+([a-zA-Z0-9]+)$/', $text, $matches)) {
        $issueDate = $matches[1];
        $lotteryNumber = $matches[2];
        
        $conn = getDbConnection();
        
        $stmt = $conn->prepare("INSERT INTO lottery_numbers (issue_date, number) VALUES (?, ?) ON DUPLICATE KEY UPDATE number = VALUES(number)");
        $stmt->bind_param("ss", $issueDate, $lotteryNumber);
        
        if ($stmt->execute()) {
            // 可以选择性地回复管理员，告知操作成功
            // file_get_contents("https://api.telegram.org/bot{$botToken}/sendMessage?chat_id={$adminId}&text=号码 {$lotteryNumber} 已记录");
        }
        
        $stmt->close();
        $conn->close();
    }
}
?>