<?php
// index.php

// --- 基础配置 ---
header("Content-Type: application/json");
// 允许来自前端的请求 (虽然_worker.js处理了，但保留作为备用)
header("Access-Control-Allow-Origin: https://ss.wenxiuxiu.eu.org");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

// 处理 OPTIONS 预检请求
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    exit(0);
}

// --- 引入模块 ---
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/api/auth.php';
require_once __DIR__ . '/api/lottery.php';

// --- 获取数据库连接 ---
$conn = get_db_connection();

// --- 路由逻辑 ---
$action = $_GET['action'] ?? '';
$response = [];

switch ($action) {
    case 'register':
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $response = handle_register($conn);
        }
        break;
    case 'login':
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $response = handle_login($conn);
        }
        break;
    case 'get_numbers':
        // 为简化，我们假设登录后才能查看，真实项目需要Token验证
        // 这里暂时开放给所有请求
        $response = handle_get_numbers($conn);
        break;
    case 'add_number':
        // 此接口需要密钥验证，支持GET或POST
        $response = handle_add_number($conn);
        break;
    default:
        http_response_code(404);
        $response = ['success' => false, 'message' => '未知的API操作'];
        break;
}

// --- 返回JSON响应 ---
if (!empty($response)) {
    echo json_encode($response);
}

// --- 关闭数据库连接 ---
$conn->close();
?>