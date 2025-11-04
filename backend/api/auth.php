<?php
// api/auth.php

function handle_register($conn) {
    $data = json_decode(file_get_contents('php://input'), true);
    $email = $data['email'] ?? null;
    $password = $data['password'] ?? null;

    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['success' => false, 'message' => '无效的邮箱地址'];
    }
    if (empty($password) || strlen($password) < 6) {
        return ['success' => false, 'message' => '密码长度至少为6位'];
    }

    $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows > 0) {
        return ['success' => false, 'message' => '该邮箱已被注册'];
    }
    $stmt->close();

    $password_hash = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $conn->prepare("INSERT INTO users (email, password_hash) VALUES (?, ?)");
    $stmt->bind_param("ss", $email, $password_hash);

    if ($stmt->execute()) {
        return ['success' => true, 'message' => '注册成功'];
    } else {
        return ['success' => false, 'message' => '注册失败，请稍后再试'];
    }
}

function handle_login($conn) {
    $data = json_decode(file_get_contents('php://input'), true);
    $email = $data['email'] ?? null;
    $password = $data['password'] ?? null;

    if (empty($email) || empty($password)) {
        return ['success' => false, 'message' => '邮箱或密码不能为空'];
    }

    $stmt = $conn->prepare("SELECT id, email, password_hash FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($user = $result->fetch_assoc()) {
        if (password_verify($password, $user['password_hash'])) {
            // 在真实项目中，这里会生成一个JWT或Session Token
            // 为简化，我们只返回成功状态和用户信息
            return ['success' => true, 'message' => '登录成功', 'user' => ['email' => $user['email']]];
        }
    }
    
    return ['success' => false, 'message' => '邮箱或密码错误'];
}
?>