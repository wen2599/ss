<?php
// File: backend/auth/login.php

require_once __DIR__ . '/../db_operations.php';

// 1. 获取 POST 请求的 JSON 数据
$data = json_decode(file_get_contents('php://input'), true);
$email = $data['email'] ?? null;
$password = $data['password'] ?? null;

// 2. 基本验证
if (empty($email) || empty($password)) {
    http_response_code(400); // Bad Request
    echo json_encode(['status' => 'error', 'message' => '邮箱和密码不能为空。']);
    exit;
}

try {
    // 3. 从数据库中查找用户
    $pdo = get_db_connection();
    $stmt = $pdo->prepare("SELECT id, email, password_hash, status FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    // 4. 验证用户是否存在、密码是否正确以及账户状态
    if ($user && password_verify($password, $user['password_hash'])) {
        
        // 检查用户是否被封禁
        if ($user['status'] === 'banned') {
            http_response_code(403); // Forbidden
            echo json_encode(['status' => 'error', 'message' => '您的账户已被封禁。']);
            exit;
        }

        // 5. 密码正确，创建 Session
        // api_header.php 已经调用了 session_start()
        session_regenerate_id(true); // 防止会话固定攻击
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_email'] = $user['email'];

        // 6. 返回成功响应和用户信息
        http_response_code(200);
        echo json_encode([
            'status' => 'success',
            'message' => '登录成功！',
            'user' => [
                'id' => $user['id'],
                'email' => $user['email']
            ]
        ]);

    } else {
        // 用户不存在或密码错误
        http_response_code(401); // Unauthorized
        echo json_encode(['status' => 'error', 'message' => '邮箱或密码错误。']);
    }

} catch (PDOException $e) {
    // 数据库错误
    error_log("Login Error: " . $e->getMessage()); // 记录错误日志
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => '服务器内部错误，请稍后再试。']);
}
?>