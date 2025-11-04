<?php
// backend/get_lottery_results.php (FIXED AND ENHANCED LOGGING)

require_once __DIR__ . '/api_header.php';
require_once __DIR__ . '/db_operations.php'; // This now loads .env automatically

function write_lottery_debug_log($message) {
    $logFile = __DIR__ . '/lottery_debug.log';
    file_put_contents($logFile, date('[Y-m-d H:i:s]') . ' [LOTTERY_API] ' . $message . PHP_EOL, FILE_APPEND | LOCK_EX);
}

write_lottery_debug_log("------ get_lottery_results.php Entry Point ------");

$pdo = get_db_connection();
if (is_array($pdo) && isset($pdo['db_error'])) {
    $errorMsg = "Database connection error: " . $pdo['db_error'];
    write_lottery_debug_log($errorMsg);
    http_response_code(503);
    echo json_encode(['status' => 'error', 'message' => $errorMsg]);
    exit;
}
if (!$pdo) {
    $errorMsg = "Database connection returned null.";
    write_lottery_debug_log($errorMsg);
    http_response_code(503);
    echo json_encode(['status' => 'error', 'message' => $errorMsg]);
    exit;
}
write_lottery_debug_log("Database connection successful."); // ADDED LOG

try {
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
    $lotteryType = isset($_GET['lottery_type']) ? urldecode($_GET['lottery_type']) : null;

    write_lottery_debug_log("Received parameters: limit={$limit}, lotteryType='{$lotteryType}'");

    $sql = "SELECT id, lottery_type, issue_number, numbers, source, received_at FROM lottery_numbers ";
    $params = [];

    if ($lotteryType) {
        $sql .= " WHERE lottery_type = ?";
        $params[] = $lotteryType;
    }

    $sql .= " ORDER BY received_at DESC, issue_number DESC LIMIT ?";
    $params[] = $limit;

    write_lottery_debug_log("Preparing SQL: ". $sql . " with params: " . json_encode($params)); // ADDED LOG

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

    write_lottery_debug_log("Fetched " . count($results) . " raw results from DB.");

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