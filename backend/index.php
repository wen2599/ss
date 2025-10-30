<?php
// backend/index.php

// --- Universal Headers ---
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");

// --- Includes ---
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/secrets.php';
require_once __DIR__ . '/routes/get_numbers.php';
require_once __DIR__ . '/routes/post_telegram.php';
require_once __DIR__ . '/routes/post_email.php';
require_once __DIR__ . '/routes/get_emails.php'; // 引入新的邮件路由

// --- Error Handling & DB Connection ---
try {
    $conn = get_db_connection();
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    exit;
}

// --- Routing Logic ---
$request_method = $_SERVER['REQUEST_METHOD'];
$request_uri = $_SERVER['REQUEST_URI'];
$path = parse_url($request_uri, PHP_URL_PATH);
$path_parts = explode('/', trim($path, '/'));

// 根据请求方法进行路由
if ($request_method === 'GET') {
    // 如果路径是 /emails
    if (isset($path_parts[0]) && $path_parts[0] === 'emails') {
        // 如果路径是 /emails/{id}
        if (isset($path_parts[1]) && is_numeric($path_parts[1])) {
            handle_get_email_by_id($conn, (int)$path_parts[1]);
        } else {
            // 如果路径是 /emails
            handle_get_emails($conn);
        }
    } else {
        // 默认 GET 请求，或对 /numbers 的请求
        handle_get_numbers($conn);
    }
} elseif ($request_method === 'POST') {
    $json_data = file_get_contents('php://input');
    $data = json_decode($json_data, true);

    if (!$data || !isset($data['token'])) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Invalid request or missing token.']);
        exit;
    }

    // 获取密钥
    $telegram_secret_token = get_env('TELEGRAM_SECRET_TOKEN');
    $email_handler_secret = get_env('EMAIL_HANDLER_SECRET');

    // 根据 Token 路由 POST 请求 (保持现有逻辑)
    if (isset($data['token']) && $data['token'] === $telegram_secret_token) {
        handle_post_telegram($conn, $data);
    } elseif (isset($data['token']) && $data['token'] === $email_handler_secret) {
        handle_post_email($conn, $data);
    } else {
        http_response_code(403);
        echo json_encode(['status' => 'error', 'message' => 'Permission denied. Invalid token.']);
    }
} else {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Method Not Allowed.']);
}

// --- Close Connection ---
$conn->close();
?>
