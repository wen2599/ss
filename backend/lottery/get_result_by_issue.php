<?php
// File: backend/lottery/get_result_by_issue.php

$issue_number = $_GET['issue'] ?? null;
$lottery_type = $_GET['type'] ?? null;

if (empty($issue_number) || empty($lottery_type)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Lottery type and issue number are required.']);
    exit;
}

try {
    $pdo = get_db_connection();
    $stmt = $pdo->prepare("SELECT * FROM lottery_results WHERE lottery_type = ? AND issue_number = ?");
    $stmt->execute([$lottery_type, $issue_number]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($result) {
        // 解码 JSON 字段
        foreach(['winning_numbers', 'zodiac_signs', 'colors'] as $key) {
            $result[$key] = json_decode($result[$key]) ?: [];
        }
        echo json_encode(['status' => 'success', 'data' => $result]);
    } else {
        http_response_code(404);
        echo json_encode(['status' => 'error', 'message' => 'Lottery result for this issue not found.']);
    }

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Database error.']);
}
?>