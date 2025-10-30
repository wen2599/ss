
<?php
/**
 * 文件名: get_latest.php
 * 路径: backend/api/lottery/get_latest.php
 * 描述: 获取最新一期的彩票开奖结果。
 * 版本: Final - Direct CORS Communication
 */

//======================================================================
// 1. 强制 CORS (跨域资源共享) 响应头
//    这是解决浏览器跨域问题的核心。
//    这段代码必须在任何其他内容输出之前执行。
//======================================================================
header("Access-Control-Allow-Origin: https://ss.wenxiuxiu.eu.org");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Access-control-allow-credentials: true");

// 当浏览器发送复杂请求(例如带自定义头的GET或任何POST请求)前，
// 会先发送一个 OPTIONS "预检"请求来询问服务器是否允许。
// 我们需要在这里处理它，并返回一个成功的响应。
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(204); // 204 No Content 是处理预检请求的标准方式
    exit;
}


//======================================================================
// 2. 错误报告 (用于开发和调试)
//    在生产环境中，建议将 display_errors 设置为 0，只保留 error_log。
//======================================================================
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);


//======================================================================
// 3. 引入核心文件
//    使用 __DIR__ 来构建绝对路径，确保在任何环境下都能正确找到文件。
//======================================================================
require_once __DIR__ . '/../../core/db.php';
require_once __DIR__ . '/../../core/helpers.php';


//======================================================================
// 4. 主业务逻辑
//    尝试连接数据库并执行查询。
//======================================================================
try {
    // 获取数据库连接实例
    $db = get_db_connection();
    
    // 准备并执行 SQL 查询，获取最新的一条记录
    // ORDER BY draw_date DESC, id DESC 确保总是拿到最新的
    $stmt = $db->query("SELECT * FROM lottery_results ORDER BY draw_date DESC, id DESC LIMIT 1");
    
    // 从查询结果中获取一行数据
    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    // 检查是否有查询结果
    if ($result) {
        // 如果有结果，使用我们的辅助函数返回一个成功的 JSON 响应
        json_response($result, 200);
    } else {
        // 如果表是空的，返回一个 404 Not Found 的 JSON 响应
        json_response(['message' => 'No lottery results found.'], 404);
    }

} catch (PDOException $e) {
    // 捕获所有 PDO (数据库) 相关的异常
    // 将详细错误信息记录到服务器日志中
    error_log("API Database Error in get_latest.php: " . $e->getMessage());
    // 向客户端返回一个通用的服务器错误信息，同时附带详细错误以便调试
    json_response([
        'message' => 'A database error occurred.',
        'error_details' => $e->getMessage()
    ], 500);
        
} catch (Exception $e) {
    // 捕获所有其他类型的异常
    error_log("API General Error in get_latest.php: " . $e->getMessage());
    json_response([
        'message' => 'An unexpected error occurred.',
        'error_details' => $e->getMessage()
    ], 500);
}
