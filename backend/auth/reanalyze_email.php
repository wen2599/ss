<?php
// File: backend/auth/reanalyze_email.php

// 核心依赖由 index.php 加载

// 1. 身份验证检查
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

// 2. 获取输入参数
$input = json_decode(file_get_contents('php://input'), true);
$email_id = $input['email_id'] ?? null;

if (empty($email_id)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Email ID is required.']);
    exit;
}

// 3. 验证邮件属于当前用户
try {
    $pdo = get_db_connection();
    
    $stmt = $pdo->prepare("SELECT id FROM raw_emails WHERE id = ? AND user_id = ?");
    $stmt->execute([$email_id, $_SESSION['user_id']]);
    
    if (!$stmt->fetch()) {
        http_response_code(403);
        echo json_encode(['status' => 'error', 'message' => 'Access denied.']);
        exit;
    }
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Database error.']);
    exit;
}

// 4. 执行重新解析
require_once __DIR__ . '/../ai_helper.php';
$result = reanalyzeEmailWithAI($email_id);

if ($result['success']) {
    http_response_code(200);
    echo json_encode([
        'status' => 'success',
        'message' => $result['message'],
        'batch_id' => $result['batch_id'] ?? null
    ]);
} else {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => $result['message']
    ]);
}
?>