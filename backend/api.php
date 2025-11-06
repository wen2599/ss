<?php
// backend/api.php

require_once 'database.php';

// 设置响应头为 JSON
header('Content-Type: application/json');

// 这里允许所有来源的请求，因为最终是由 Cloudflare Worker 控制的
// 但在后端也设置一下是良好的实践
header('Access-Control-Allow-Origin: *'); 

try {
    $latest_number_data = Database::getLatestLotteryNumber();

    if ($latest_number_data) {
        $response = [
            'success' => true,
            'data' => [
                'number' => $latest_number_data['number'],
                'time' => $latest_number_data['draw_time']
            ]
        ];
    } else {
        $response = [
            'success' => false,
            'message' => 'No lottery numbers found.'
        ];
    }
} catch (\Exception $e) {
    http_response_code(500);
    $response = [
        'success' => false,
        'message' => 'An internal server error occurred.'
    ];
    // 错误日志已在 Database 类中记录
}

echo json_encode($response);
?>