<?php
// File: backend/index.php

// 1. 加载所有核心公共文件
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db_operations.php';
require_once __DIR__ . '/api_header.php';

// 2. 获取端点参数
$endpoint = $_GET['endpoint'] ?? null;

if ($endpoint === null) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Missing endpoint parameter.']);
    exit();
}

// 3. 定义路由
$routes = [
    'register' => 'auth/register.php',
    'login' => 'auth/login.php',
    'logout' => 'auth/logout.php',
    'check_session' => 'auth/check_session.php',
    'get_lottery_results' => 'lottery/get_results.php',
    'get_emails' => 'auth/get_emails.php', // <-- 【新增路由】
];

// 4. 根据路由加载对应的端点文件
if (isset($routes[$endpoint])) {
    require_once __DIR__ . '/' . $routes[$endpoint];
} else {
    http_response_code(404);
    echo json_encode(['status' => 'error', 'message' => 'Endpoint not found.']);
}