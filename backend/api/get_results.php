<?php
// backend/api/get_results.php

// 严格错误报告
ini_set('display_errors', 1);
error_reporting(E_ALL);

// 设置 JSON 响应头
header('Content-Type: application/json');

// 引入 CORS 头部设置
require_once __DIR__ . '/cors_headers.php';

try {
    // 引入数据库连接
    require_once __DIR__ . '/../db_connection.php';

    // 检查数据库连接是否成功
    if ($conn->connect_error) {
        throw new Exception("数据库连接失败: ". $conn->connect_error);
    }

    $results = [];
    $sql = "SELECT id, issue_number, draw_date, numbers, created_at FROM lottery_results ORDER BY issue_number DESC LIMIT 100";
    $result = $conn->query($sql);

    // 检查查询是否成功
    if ($result === false) {
        throw new Exception("查询执行失败: ". $conn->error);
    }

    if ($result->num_rows > 0) {
        while($row = $result->fetch_assoc()) {
            $results[] = $row;
        }
    }

    // 关闭连接
    $conn->close();

    // 返回成功响应
    echo json_encode(['success' => true, 'data' => $results]);

} catch (Exception $e) {
    // 设置 HTTP 状态码为 500
    http_response_code(500);
    // 返回失败响应
    echo json_encode(['success' => false, 'message' => '服务器内部错误: '. $e->getMessage()]);
}
?>