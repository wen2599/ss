<?php
// backend/get_lottery_results.php

require_once __DIR__ . '/api_header.php';
require_once __DIR__ . '/db_operations.php';

// Custom Debug Logging Function for lottery results
function write_lottery_debug_log($message) {
    $logFile = __DIR__ . '/lottery_debug.log';
    file_put_contents($logFile, date('[Y-m-d H:i:s]') . ' [LOTTERY_API] ' . $message . PHP_EOL, FILE_APPEND | LOCK_EX);
}

write_lottery_debug_log("------ get_lottery_results.php Entry Point ------");

// --- Authentication Check (Optional, depending on if lottery results are public) ---
// If you want lottery results to be public, comment out or remove this block.
// If (!isset($_SESSION['user_id'])) {
//     http_response_code(401); // Unauthorized
//     echo json_encode(['status' => 'error', 'message' => 'You must be logged in to view lottery results.']);
//     exit;
// }

// --- Fetch Lottery Results ---
$pdo = get_db_connection();
if (is_array($pdo) && isset($pdo['db_error'])) {
    $errorMsg = "Database connection is currently unavailable: " . $pdo['db_error'];
    write_lottery_debug_log($errorMsg);
    http_response_code(503); // Service Unavailable
    echo json_encode(['status' => 'error', 'message' => $errorMsg]);
    exit;
}
if (!$pdo) {
    $errorMsg = "Database connection is currently unavailable (returned null).";
    write_lottery_debug_log($errorMsg);
    http_response_code(503); // Service Unavailable
    echo json_encode(['status' => 'error', 'message' => $errorMsg]);
    exit;
}

try {
    // Fetch the latest lottery result, or a specific number of results
    // For example, fetching the latest 10 results
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
    $lotteryType = isset($_GET['lottery_type']) ? $_GET['lottery_type'] : null;

    write_lottery_debug_log("Received parameters: limit={$limit}, lotteryType='{$lotteryType}'");

    $sql = "SELECT id, lottery_type, issue_number, winning_numbers, zodiac_signs, colors, drawing_date, created_at FROM lottery_results ";
    $params = [];

    if ($lotteryType) {
        $sql .= " WHERE lottery_type = ?";
        $params[] = $lotteryType;
    }

    $sql .= " ORDER BY drawing_date DESC, issue_number DESC LIMIT ?";
    $params[] = $limit;

    write_lottery_debug_log("Executing SQL: '{$sql}' with params: " . json_encode($params));

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

    write_lottery_debug_log("Fetched " . count($results) . " results.");

    echo json_encode(['status' => 'success', 'lottery_results' => $results]);

} catch (PDOException $e) {
    $errorMsg = "Error fetching lottery results: " . $e->getMessage();
    error_log($errorMsg);
    write_lottery_debug_log($errorMsg);
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'An error occurred while fetching lottery results.']);
}

write_lottery_debug_log("------ get_lottery_results.php Exit Point ------");

?>