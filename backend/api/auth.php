<?php
// backend/api/auth.php

require_once __DIR__ . '/../config/database.php';

function handle_register() {
    $data = json_decode(file_get_contents('php://input'), true);

    if (empty($data['email']) || empty($data['password'])) {
        http_response_code(400);
        echo json_encode(['message' => '邮箱和密码不能为空']);
        return;
    }

    $email = $data['email'];
    // 基本的邮箱格式验证
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        http_response_code(400);
        echo json_encode(['message' => '无效的邮箱格式']);
        return;
    }

    // 密码哈希处理，这是保证安全的关键！
    $password_hash = password_hash($data['password'], PASSWORD_DEFAULT);

    $mysqli = get_db_connection();

    // 检查邮箱是否已被注册
    $stmt = $mysqli->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows > 0) {
        http_response_code(409); // 409 Conflict
        echo json_encode(['message' => '该邮箱已被注册']);
        $stmt->close();
        $mysqli->close();
        return;
    }
    $stmt->close();

    // 插入新用户
    $stmt_insert = $mysqli->prepare("INSERT INTO users (email, password) VALUES (?, ?)");
    $stmt_insert->bind_param("ss", $email, $password_hash);

    if ($stmt_insert->execute()) {
        http_response_code(201); // 201 Created
        echo json_encode(['message' => '注册成功']);
    } else {
        http_response_code(500);
        echo json_encode(['message' => '注册失败，请稍后再试']);
    }

    $stmt_insert->close();
    $mysqli->close();
}

function handle_login() {
    $data = json_decode(file_get_contents('php://input'), true);

    if (empty($data['email']) || empty($data['password'])) {
        http_response_code(400);
        echo json_encode(['message' => '邮箱和密码不能为空']);
        return;
    }

    $email = $data['email'];
    $password = $data['password'];

    $mysqli = get_db_connection();
    $stmt = $mysqli->prepare("SELECT id, email, password FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($user = $result->fetch_assoc()) {
        // 验证密码哈希
        if (password_verify($password, $user['password'])) {
            // 登录成功
            // 提示：在更复杂的应用中，这里会生成一个 JWT (JSON Web Token) 并返回给前端
            http_response_code(200);
            echo json_encode([
                'message' => '登录成功',
                'user' => [
                    'id' => $user['id'],
                    'email' => $user['email']
                ]
            ]);
        } else {
            // 密码错误
            http_response_code(401);
            echo json_encode(['message' => '邮箱或密码错误']);
        }
    } else {
        // 用户不存在
        http_response_code(401);
        echo json_encode(['message' => '邮箱或密码错误']);
    }

    $stmt->close();
    $mysqli->close();
}