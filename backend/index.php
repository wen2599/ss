<?php
// backend/index.php

// ================= 临时调试代码 =================
// 目的：强制在浏览器中显示详细的PHP错误，方便定位问题。
// 警告：在生产环境中应移除或禁用这些代码，以防泄露敏感信息。
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
// ===============================================


// --- 基础配置 ---
header("Content-Type: application/json");
// 允许来自前端的请求 (虽然_worker.js处理了，但保留作为备用)
header("Access-Control-Allow-Origin: https://ss.wenxiuxiu.eu.org");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

// 处理 OPTIONS 预检请求
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit;
}

// --- 引入模块 ---
// 使用 __DIR__ 确保从当前文件所在目录开始查找，这是最可靠的方式
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/api/auth.php';
require_once __DIR__ . '/api/lottery.php';

// --- 获取数据库连接 ---
$conn = get_db_connection();

// --- 路由逻辑 ---
$action = $_GET['action'] ?? '';
$response = [];

try {
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
            $response = handle_get_numbers($conn);
            break;
        case 'add_number':
            $response = handle_add_number($conn);
            break;
        default:
            http_response_code(404);
            $response = ['success' => false, 'message' => '未知的API操作'];
            break;
    }
} catch (Exception $e) {
    http_response_code(500);
    $response = ['success' => false, 'message' => '服务器内部错误: ' . $e->getMessage()];
}


// --- 返回JSON响应 ---
if (!empty($response)) {
    echo json_encode($response);
}

// --- 关闭数据库连接 ---
$conn->close();
?>