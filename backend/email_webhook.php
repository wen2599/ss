<?php
/**
 * 文件名: email_webhook.php
 * 路径: backend/ (项目根目录)
 * 描述: 接收并处理来自 Cloudflare Worker 的邮件数据。
 */
ini_set('display_errors', 0);
error_reporting(E_ALL);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/email_errors.log');

require_once __DIR__ . '/core/db.php';

// --- 1. 安全性验证 ---
// 验证请求是否来自我们的 Worker
$secret_header = $_SERVER['HTTP_X_WEBHOOK_SECRET'] ?? '';
if (hash_equals(EMAIL_WEBHOOK_SECRET, $secret_header) === false) {
    http_response_code(403);
    error_log('Invalid Email Webhook Secret. Access denied.');
    exit('Forbidden');
}

// --- 2. 获取并解析输入 ---
$data = json_decode(file_get_contents('php://input'), true);
if (!$data) {
    http_response_code(400);
    exit('Bad Request: No data received.');
}

$from_address = $data['from'] ?? null;
$subject = $data['subject'] ?? '';
$raw_content = $data['raw_content'] ?? '';

if (empty($from_address)) {
    http_response_code(400);
    error_log('Bad Request: Missing "from" address in payload.');
    exit('Bad Request: Missing "from" address.');
}

try {
    $db = get_db_connection();

    // --- 3. 根据发件人邮箱查找用户 ---
    $stmt = $db->prepare("SELECT id, is_authorized FROM users WHERE email = ?");
    $stmt->execute([$from_address]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        // 如果找不到用户，可以选择静默失败或记录日志
        error_log("Email received from non-registered user: {$from_address}");
        http_response_code(200); // 仍然返回 200，避免 Worker 重试
        exit('OK: User not found.');
    }

    $user_id = $user['id'];
    $is_authorized = $user['is_authorized'];

    // --- 4. 将邮件存入数据库 ---
    $stmt = $db->prepare(
        "INSERT INTO received_emails (user_id, from_address, subject, raw_content, status) 
         VALUES (?, ?, ?, ?, ?)"
    );
    // 初始状态都设为 'new'，后续再由 AI 触发处理
    $stmt->execute([$user_id, $from_address, $subject, $raw_content, 'new']);
    
    // 可以在这里扩展逻辑：如果 $is_authorized 为 true，则立即触发一个后台任务来调用 AI
    // if ($is_authorized) {
    //     // ... call AI processing logic ...
    // }

    // --- 5. 返回成功响应 ---
    http_response_code(200);
    echo "OK: Email received and stored.";

} catch (PDOException $e) {
    error_log("Email Webhook DB Error: " . $e->getMessage());
    http_response_code(500);
    exit('Internal Server Error.');
} catch (Exception $e) {
    error_log("Email Webhook Unhandled Exception: " . $e->getMessage());
    http_response_code(500);
    exit('Internal Server Error.');
}
?>