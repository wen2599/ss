<?php
// index.php - Backend Router

// --- 安全与错误处理 ---
ini_set('display_errors', 0); // 在生产环境中关闭错误显示
error_reporting(E_ALL);
set_error_handler(function ($severity, $message, $file, $line) {
    throw new ErrorException($message, 0, $severity, $file, $line);
});

try {
    // --- 路由逻辑 ---
    
    // 获取请求路径，移除查询字符串
    $request_uri = strtok($_SERVER['REQUEST_URI'], '?');

    // 根据请求路径分发到不同的处理文件
    switch ($request_uri) {
        // 前端 API 请求的路由
        case '/api/get_results':
            require_once __DIR__ . '/api.php';
            break;

        // Telegram Webhook 请求的路由
        case '/webhook':
            require_once __DIR__ . '/webhook.php';
            break;

        // 如果没有匹配的路由，返回 404 Not Found
        default:
            http_response_code(404);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Endpoint not found.']);
            break;
    }

} catch (Exception $e) {
    // 捕获任何未处理的异常，返回 500 错误
    error_log("Router Error: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine());
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Internal Server Error']);
}