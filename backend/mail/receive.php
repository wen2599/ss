<?php
// backend/mail/receive.php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../db_operations.php';

// 1. 安全验证 (读取 Authorization Header)
$secret = config('EMAIL_WORKER_SECRET');
$auth_header = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
$token = str_replace('Bearer ', '', $auth_header);

if (!$secret || !hash_equals($secret, $token)) { // 使用 hash_equals 防止时序攻击
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Forbidden: Invalid token.']);
    exit;
}

// 2. 获取 JSON Body
$input = json_decode(file_get_contents('php://input'), true);
$sender_email = $input['sender'] ?? null;
$raw_content = $input['raw_content'] ?? null;

if (empty($sender_email) || empty($raw_content)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Missing sender or raw_content.']);
    exit;
}

try {
    $pdo = get_db_connection();
    // 3. 查找用户
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND status = 'active'");
    $stmt->execute([$sender_email]);
    $user_id = $stmt->fetchColumn();

    if (!$user_id) {
        error_log("Email received from unregistered/banned user: {$sender_email}");
        http_response_code(200);
        echo json_encode(['status' => 'success', 'message' => 'User not processed.']);
        exit;
    }

    // 4. 存入数据库
    $stmt = $pdo->prepare("INSERT INTO raw_emails (user_id, content) VALUES (?, ?)");
    $stmt->execute([$user_id, $raw_content]);

    http_response_code(200);
    echo json_encode(['status' => 'success', 'message' => 'Email received and stored.']);

} catch (PDOException $e) {
    error_log("Error in mail/receive.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Database error.']);
}
?>