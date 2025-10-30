<?php
/**
 * 文件名: api.php
 * 路径: backend/api.php
 */

// 1. CORS 头
header("Access-Control-Allow-Origin: https://ss.wenxiuxiu.eu.org");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') { http_response_code(204); exit; }

// 2. 错误报告 (调试时开启)
ini_set('display_errors', 1);
error_reporting(E_ALL);

// 3. 引入核心文件
require_once __DIR__ . '/core/db.php';
require_once __DIR__ . '/core/helpers.php';

// 4. API 路由
$action = $_GET['action'] ?? '';
if ($action === 'get_latest_lottery') {
    handle_get_latest_lottery();
} else {
    json_response(['message' => 'Invalid API action'], 404);
}

// 5. 处理器函数
function handle_get_latest_lottery() {
    try {
        $db = get_db_connection();
        $stmt = $db->query("SELECT * FROM lottery_results ORDER BY draw_date DESC, id DESC LIMIT 1");
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($result) {
            json_response($result, 200);
        } else {
            json_response(['message' => 'No lottery results found.'], 404);
        }
    } catch (PDOException $e) {
        error_log("API Error: " . $e->getMessage());
        json_response(['message' => 'A database error occurred.'], 500);
    }
}
?>