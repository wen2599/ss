<?php
// backend/api/index.php

header("Content-Type: application/json");

// 简单的路由
$action = $_GET['action'] ?? '';

// 根据 action 调用不同的处理器
switch ($action) {
    case 'register':
        require __DIR__ . '/../handlers/register.php';
        break;
    case 'login':
        require __DIR__ . '/../handlers/login.php';
        break;
    case 'get_numbers':
        require __DIR__ . '/../handlers/get_lottery_numbers.php';
        break;
    default:
        http_response_code(404);
        echo json_encode(['error' => 'Action Not Found']);
        break;
}
?>
