<?php
// 文件名: get_latest.php
// 路径: backend/api/lottery/get_latest.php
// 版本: Final Fix

// --- 强制开启最详细的错误报告 ---
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

/**
 * 引入核心文件
 * 
 * 使用 `__DIR__` 来构建绝对路径，这是最可靠的方式，可以避免任何“当前工作目录”的问题。
 * `__DIR__` -> /usr/home/wenge95222/domains/wenge.cloudns.ch/public_html/api/lottery
 * `__DIR__ . '/../../'` -> /usr/home/wenge95222/domains/wenge.cloudns.ch/public_html/
 */
require_once __DIR__ . '/../../core/db.php';
require_once __DIR__ . '/../../core/helpers.php';

// 在这里，我们不再需要显式设置 CORS 头，因为前端代理(_worker.js)会处理它。
// 但为了直接访问测试，暂时保留 Content-Type 头。
header("Content-Type: application/json; charset=UTF-8");

try {
    // 1. 获取数据库连接
    $db = get_db_connection();
    
    // 2. 执行数据库查询
    $stmt = $db->query("SELECT * FROM lottery_results ORDER BY draw_date DESC, id DESC LIMIT 1");
    
    // 3. 获取结果
    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    // 4. 根据结果返回 JSON 响应
    if ($result) {
        // 如果有结果，使用我们定义的辅助函数返回200 OK
        json_response($result, 200);
    } else {
        // 如果没有结果，返回404 Not Found
        json_response(['message' => 'No lottery results found.'], 404);
    }

} catch (PDOException $e) {
    // 如果在数据库操作过程中出现任何错误
    error_log("API DB Error in get_latest.php: " . $e->getMessage());
    json_response([
        'message' => 'A database error occurred.',
        'error_details' => $e->getMessage() // 在调试期间保留详细错误
    ], 500);
        
} catch (Exception $e) {
    // 捕获其他可能的错误
    error_log("API General Error in get_latest.php: " . $e->getMessage());
    json_response([
        'message' => 'An unexpected error occurred.',
        'error_details' => $e->getMessage()
    ], 500);
}