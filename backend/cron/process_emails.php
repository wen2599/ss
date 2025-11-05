<?php
// 这个脚本应该由 cron job 调用，例如每分钟一次
// 在 Serv00 后台设置 cron: * * * * * /usr/bin/php /path/to/your/backend/cron/process_emails.php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../controllers/AiController.php';

$pdo = get_db_connection();
$aiController = new AiController();

// 1. 查找待处理的邮件
$stmt = $pdo->prepare("SELECT id, email_content FROM bets_raw_emails WHERE status = 'pending' LIMIT 5"); // 一次处理5封，防止超时
$stmt->execute();
$emails = $stmt->fetchAll();

if (empty($emails)) {
    echo "没有需要处理的邮件。\n";
    exit;
}

foreach ($emails as $email) {
    $emailId = $email['id'];
    
    // 2. 标记为处理中
    $updateStmt = $pdo->prepare("UPDATE bets_raw_emails SET status = 'processing' WHERE id = ?");
    $updateStmt->execute([$emailId]);

    // 3. 调用 AI 解析
    $result = $aiController->parseEmailWithCloudflareAI($email['email_content']);
    
    // 4. 更新结果
    if (isset($result['response']) && !isset(json_decode($result['response'], true)['error'])) {
        $updateStmt = $pdo->prepare("UPDATE bets_raw_emails SET status = 'processed', ai_result = ? WHERE id = ?");
        $updateStmt->execute([$result['response'], $emailId]);
        echo "邮件 #{$emailId} 处理成功。\n";
    } else {
        // 这里可以加入调用 Gemini 的逻辑作为备用
        $errorMsg = $result['error'] ?? json_encode($result);
        $updateStmt = $pdo->prepare("UPDATE bets_raw_emails SET status = 'error', error_message = ? WHERE id = ?");
        $updateStmt->execute([$errorMsg, $emailId]);
        echo "邮件 #{$emailId} 处理失败: {$errorMsg}\n";
    }
}
