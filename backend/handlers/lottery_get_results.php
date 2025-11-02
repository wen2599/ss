<?php
// backend/handlers/lottery_get_results.php

// 这是一个公开接口，不需要JWT验证

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    send_json_response(['status' => 'error', 'message' => 'Invalid request method.'], 405);
}

try {
    // 查询最新的20条记录，按期号降序排列
    $stmt = $pdo->query("SELECT issue_number, numbers, draw_date FROM lottery_results ORDER BY issue_number DESC LIMIT 20");
    $results = $stmt->fetchAll();
    
    send_json_response(['status' => 'success', 'data' => $results]);

} catch (PDOException $e) {
    error_log("Get lottery results error: " . $e->getMessage());
    send_json_response(['status' => 'error', 'message' => 'Failed to fetch lottery results.'], 500);
}
