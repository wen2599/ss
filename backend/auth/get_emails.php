<?php
// File: backend/auth/get_emails.php

// 核心依赖由 index.php 加载，这里我们直接使用

// 1. 身份验证检查
if (!isset($_SESSION['user_id'])) {
    http_response_code(401); // Unauthorized
    echo json_encode(['status' => 'error', 'message' => '您需要登录才能查看邮件。']);
    exit;
}

$user_id = $_SESSION['user_id'];
$limit = 20; // 限制一次最多获取20封邮件

try {
    $pdo = get_db_connection();

    // 2. 从数据库查询邮件
    // 我们只查询 raw_emails 表，因为前端列表只需要邮件的基本信息
    // 关键：WHERE user_id = ? 确保了用户只能看到自己的邮件
    $stmt = $pdo->prepare(
        "SELECT id, status, received_at 
         FROM raw_emails 
         WHERE user_id = ? 
         ORDER BY received_at DESC 
         LIMIT ?"
    );
    
    // PDO::PARAM_INT 告诉数据库这是一个整数，更安全
    $stmt->bindParam(1, $user_id, PDO::PARAM_INT);
    $stmt->bindParam(2, $limit, PDO::PARAM_INT);
    $stmt->execute();
    
    $emails = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 3. 返回成功响应
    http_response_code(200);
    echo json_encode(['status' => 'success', 'data' => $emails]);

} catch (PDOException $e) {
    error_log("Error fetching emails for user {$user_id}: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => '获取邮件列表时发生数据库错误。']);
}
?>