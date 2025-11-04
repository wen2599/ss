<?php
// api.php - 数据查询接口

require_once 'db.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *'); 

$conn = get_db_connection();
$response = ['success' => false, 'data' => [], 'message' => ''];

if (!$conn) {
    $response['message'] = 'Could not connect to the database.';
    http_response_code(500);
    echo json_encode($response);
    exit;
}

// 从新表 lottery_results 查询最新的 30 条记录
$sql = "SELECT id, lottery_type, issue_number, winning_numbers, created_at FROM lottery_results ORDER BY created_at DESC LIMIT 30";
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
