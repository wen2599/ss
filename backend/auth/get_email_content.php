<?php
// File: backend/auth/get_email_content.php

// 核心依赖由 index.php 加载

// 1. 身份验证检查
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

$user_id = $_SESSION['user_id'];
$email_id = $_GET['id'] ?? null;

if (empty($email_id)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Email ID is required.']);
    exit;
}

try {
    $pdo = get_db_connection();

    // 2. 查询特定邮件的内容，并严格匹配用户ID
    $stmt = $pdo->prepare(
        "SELECT id, content 
         FROM raw_emails 
         WHERE id = ? AND user_id = ?"
    );
    
    $stmt->bindParam(1, $email_id, PDO::PARAM_INT);
    $stmt->bindParam(2, $user_id, PDO::PARAM_INT);
    $stmt->execute();
    
    $email = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($email) {
        // 3. 返回成功响应
        http_response_code(200);
        echo json_encode(['status' => 'success', 'data' => $email]);
    } else {
        http_response_code(404);
        echo json_encode(['status' => 'error', 'message' => 'Email not found or access denied.']);
    }

} catch (PDOException $e) {
    error_log("Error fetching email content for user {$user_id}, email {$email_id}: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Database error.']);
}
?>