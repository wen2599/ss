<?php
// backend/router.php

require_once __DIR__ . '/api/auth.php';
require_once __DIR__ . '/api/lottery.php';

// 非常基础的路由
$request_uri = $_SERVER['REQUEST_URI'];
$method = $_SERVER['REQUEST_METHOD'];

// 设置 JSON 响应头
header('Content-Type: application/json');

// 简单的路由逻辑
switch ($request_uri) {
    case '/api/register':
        if ($method == 'POST') {
            handle_register();
        } else {
            http_response_code(405); // Method Not Allowed
            echo json_encode(['message' => 'Method Not Allowed']);
        }
        break;

    case '/api/login':
        if ($method == 'POST') {
            handle_login();
        } else {
            http_response_code(405);
            echo json_encode(['message' => 'Method Not Allowed']);
        }
        break;

    case '/api/lottery/latest':
        if ($method == 'GET') {
            handle_get_latest_lottery_number();
        } else {
            http_response_code(405);
            echo json_encode(['message' => 'Method Not Allowed']);
        }
        break;

    default:
        http_response_code(404); // Not Found
        echo json_encode(['message' => 'Endpoint not found']);
        break;
}
