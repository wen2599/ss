<?php

// Main entry point for API requests

header('Content-Type: application/json');

$endpoint = $_GET['endpoint'] ?? null;

if ($endpoint === null) {
    http_response_code(400); // Bad Request
    echo json_encode(['error' => 'Missing endpoint parameter.']);
    exit();
}

// All endpoints are assumed to be in the backend directory
switch ($endpoint) {
    case 'register_user':
        require_once __DIR__ . '/register_user.php';
        break;
    case 'login_user':
        require_once __DIR__ . '/login_user.php';
        break;
    case 'get_emails':
        require_once __DIR__ . '/get_emails.php';
        break;
    case 'delete_bill':
        require_once __DIR__ . '/delete_bill.php';
        break;
    case 'get_lottery_results':
        require_once __DIR__ . '/get_lottery_results.php';
        break;
    case 'telegramWebhook': // 兼容旧写法
    case 'telegram_webhook': // 推荐新写法，全小写
        require_once __DIR__ . '/telegram_webhook.php'; // 文件名应为 telegram_webhook.php
        break;
    case 'process_email_ai':
        require_once __DIR__ . '/process_email_ai.php';
        break;
    // Add other endpoints as needed
    default:
        http_response_code(404); // Not Found
        echo json_encode(['error' => 'Endpoint not found.']);
        break;
}

?>
