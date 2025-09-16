<?php
// backend/api/get_logs.php

// === CORS 配置 ===
// 允许来自你的前端域名的请求
header("Access-Control-Allow-Origin: https://ss.wenxiuxiu.eu.org");
// 允许的 HTTP 方法
header("Access-Control-Allow-Methods: GET, OPTIONS");
// 允许的请求头
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
// 允许发送 Cookies (如果你的应用需要用户认证或会话管理)
header("Access-Control-Allow-Credentials: true");
// 预检请求的缓存时间 (可选，但推荐)
header("Access-Control-Max-Age: 86400");

// 对于预检请求 (OPTIONS 方法)，直接退出，不执行后续业务逻辑
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204); // No Content
    exit(0);
}

// === 错误报告设置 ===
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// === 设置响应内容类型为 JSON ===
header('Content-Type: application/json');

require_once __DIR__ . '/database.php';

$response = ['success' => false, 'message' => 'An unknown error occurred.'];

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    try {
        $pdo = getDbConnection();

        $stmt = $pdo->query("SELECT id, filename, parsed_data, created_at FROM chat_logs ORDER BY created_at DESC");
        $logs = $stmt->fetchAll();

        // The parsed_data is stored as a JSON string, so we need to decode it
        foreach ($logs as &$log) {
            $log['parsed_data'] = json_decode($log['parsed_data']);
        }

        $response = [
            'success' => true,
            'data' => $logs
        ];

    } catch (PDOException $e) {
        $response['message'] = 'Failed to retrieve logs from the database.';
        http_response_code(500);
    }
} else {
    $response['message'] = 'Only GET requests are accepted.';
    http_response_code(405); // Method Not Allowed
}

echo json_encode($response);
?>
