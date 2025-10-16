<?php
// backend/get_lottery_results.php

require_once __DIR__ . '/api_header.php';
require_once __DIR__ . '/db_operations.php';

// --- Authentication Check (Optional, depending on if lottery results are public) ---
// If you want lottery results to be public, comment out or remove this block.
// If (!isset($_SESSION['user_id'])) {
//     http_response_code(401); // Unauthorized
//     echo json_encode(['status' => 'error', 'message' => 'You must be logged in to view lottery results.']);
//     exit;
// }

// --- Fetch Lottery Results ---
$pdo = get_db_connection();
if (!$pdo) {
    http_response_code(503); // Service Unavailable
    echo json_encode(['status' => 'error', 'message' => 'Database connection is currently unavailable.']);
    exit;
}

try {
    // Fetch the latest lottery result, or a specific number of results
    // For example, fetching the latest 10 results
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
    $lotteryType = isset($_GET['lottery_type']) ? $_GET['lottery_type'] : null;

    $sql = "SELECT id, lottery_type, issue_number, winning_numbers, zodiac_signs, colors, drawing_date, created_at FROM lottery_results ";
    $params = [];

    if ($lotteryType) {
        $sql .= " WHERE lottery_type = ?";
        $params[] = $lotteryType;
    }

    $sql .= " ORDER BY drawing_date DESC, issue_number DESC LIMIT ?";
    $params[] = $limit;

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['status' => 'success', 'lottery_results' => $results]);

} catch (PDOException $e) {
    error_log("Error fetching lottery results: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'An error occurred while fetching lottery results.']);
}
