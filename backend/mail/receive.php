<?php
// File: backend/mail/receive.php

// 邮件接收是关键入口，独立加载所需依赖
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../db_operations.php';

// 1. 安全验证 (验证来自 Cloudflare Worker 的密钥)
$secret = getenv('EMAIL_WORKER_SECRET');
$auth_header = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
$token = str_replace('Bearer ', '', $auth_header);

if (!$secret || $token !== $secret) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Forbidden: Invalid or missing token.']);
    exit;
}

// 2. 获取并验证输入
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

    // 3. 根据发件人邮箱查找用户
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND status = 'active'");
    $stmt->execute([$sender_email]);
    $user_id = $stmt->fetchColumn();

    if (!$user_id) {
        // 用户不存在或被封禁，静默处理但记录日志
        error_log("Received email from unregistered or banned user: {$sender_email}");
        http_response_code(200); // 返回200防止Worker重试
        echo json_encode(['status' => 'success', 'message' => 'User not found or is inactive.']);
        exit;
    }

    // 4. 将邮件原文存入数据库
    $stmt = $pdo->prepare(
        "INSERT INTO raw_emails (user_id, content, status) VALUES (?, ?, 'pending')"
    );
    $stmt->execute([$user_id, $raw_content]);

    // TODO: 在这里可以触发一个异步的 AI 分析任务
    // 例如，可以将 lastInsertId() 写入一个消息队列或任务表

    http_response_code(200);
    echo json_encode(['status' => 'success', 'message' => 'Email received and stored.']);

} catch (PDOException $e) {
    error_log("Error in receive.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Database error during email processing.']);
}
?>