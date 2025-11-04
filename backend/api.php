<?php
// api.php - 数据查询接口

require_once 'db.php';

// 设置响应头为 JSON
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *'); // 理论上可以设为 *，因为前端代理会处理，但最好设为你的前端域名

$conn = get_db_connection();
$response = ['success' => false, 'data' => [], 'message' => ''];

if (!$conn) {
    $response['message'] = 'Could not connect to the database.';
    http_response_code(500);
    echo json_encode($response);
    exit;
}

// 查询最新的 20 条记录
$sql = "SELECT number, created_at FROM lottery_numbers ORDER BY created_at DESC LIMIT 20";
$result = $conn->query($sql);

if ($result) {
    $data = [];
    while ($row = $result->fetch_assoc()) {
        $data[] = $row;
    }
    $response['success'] = true;
    $response['data'] = $data;
} else {
    $response['message'] = 'Query failed: ' . $conn->error;
    http_response_code(500);
}

echo json_encode($response);