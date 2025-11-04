<?php
// api.php - 健壮性改进版

// 调试时取消注释下面三行
// ini_set('display_errors', 1);
// ini_set('display_startup_errors', 1);
// error_reporting(E_ALL);

require_once 'db.php';

header('Content-Type: application/json');
// 注意：即使有 worker，也最好保留这个头，作为备用
header('Access-Control-Allow-Origin: *'); 

$response = ['success' => false, 'data' => [], 'message' => 'An unknown error occurred.'];
http_response_code(500); // 默认是失败状态

try {
    $conn = get_db_connection();
    if (!$conn) {
        // 直接从 get_db_connection 中获取错误信息（如果可以的话）
        // 或者提供一个通用信息
        throw new Exception('Could not establish database connection. Check server logs.');
    }

    // 检查查询语句中的表名和字段名是否正确
    $sql = "SELECT id, lottery_type, issue_number, winning_numbers, created_at FROM lottery_results ORDER BY created_at DESC LIMIT 30";
    $result = $conn->query($sql);

    if ($result === false) {
        // 查询失败，记录具体的数据库错误
        throw new Exception('Database query failed: ' . $conn->error);
    }
    
    $data = [];
    while ($row = $result->fetch_assoc()) {
        $data[] = $row;
    }

    $response['success'] = true;
    $response['data'] = $data;
    $response['message'] = 'Data retrieved successfully.';
    http_response_code(200);

} catch (Exception $e) {
    // 捕获任何异常，并将其信息作为响应返回
    $response['message'] = $e->getMessage();
    // 可以在服务器日志中记录更详细的错误
    error_log("API Error: " . $e->getMessage());
}

echo json_encode($response);