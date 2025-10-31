<?php
// backend/api/auth.php

// 严格错误报告
ini_set('display_errors', 1);
error_reporting(E_ALL);

// 引入 CORS 头部设置
require_once __DIR__ . '/cors_headers.php';
// 引入数据库连接
require_once __DIR__ . '/../db_connection.php';

// 开启 session
session_start();

// 设置 JSON 响应头
header('Content-Type: application/json');

// 获取请求方法
$method = $_SERVER['REQUEST_METHOD'];

// 获取 action 参数
$action = $_GET['action'] ?? '';

// 简单的路由
if ($method === 'POST' && $action === 'register') {
    $data = json_decode(file_get_contents('php://input'), true);

    $email = $data['email'] ?? null;
    $password = $data['password'] ?? null;

    if (empty($email) || empty($password)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => '邮箱和密码不能为空。']);
        exit;
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => '无效的邮箱格式。']);
        exit;
    }

    $hashed_password = password_hash($password, PASSWORD_DEFAULT);

    $stmt = $conn->prepare("INSERT INTO users (email, password) VALUES (?, ?)");
    $stmt->bind_param("ss", $email, $hashed_password);

    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => '注册成功。']);
    } else {
        if ($conn->errno === 1062) {
            http_response_code(409);
            echo json_encode(['success' => false, 'message' => '该邮箱已被注册。']);
        } else {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => '注册失败，请稍后再试。']);
        }
    }
    $stmt->close();
    $conn->close();
} elseif ($method === 'POST' && $action === 'login') {
    $data = json_decode(file_get_contents('php://input'), true);

    $email = $data['email'] ?? null;
    $password = $data['password'] ?? null;

    if (empty($email) || empty($password)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => '邮箱和密码不能为空。']);
        exit;
    }

    $stmt = $conn->prepare("SELECT id, email, password FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();

    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_email'] = $user['email'];
        echo json_encode(['success' => true, 'message' => '登录成功。', 'user' => ['email' => $user['email']]]);
    } else {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => '邮箱或密码错误。']);
    }
    $stmt->close();
    $conn->close();
} elseif ($method === 'POST' && $action === 'logout') {
    session_destroy();
    echo json_encode(['success' => true, 'message' => '登出成功。']);
} elseif ($method === 'GET' && $action === 'check_session') {
    if (isset($_SESSION['user_id'])) {
        echo json_encode(['success' => true, 'loggedIn' => true, 'user' => ['email' => $_SESSION['user_email']]]);
    } else {
        echo json_encode(['success' => true, 'loggedIn' => false]);
    }
} else {
    // 无效请求
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => '无效的请求。']);
}
