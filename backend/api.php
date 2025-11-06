<?php
// api.php

// 开启日志记录，但不直接显示错误
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

require_once 'database.php';

header('Content-Type: application/json');
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
    // 记录通用错误
    error_log("API Error: " . $e->getMessage());
    http_response_code(500);
    $response = [
        'success' => false,
        'message' => 'An internal server error occurred.'
    ];
}

echo json_encode($response);
?>