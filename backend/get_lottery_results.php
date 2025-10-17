<?php
// backend/get_lottery_results.php

require_once __DIR__ . '/api_header.php';
require_once __DIR__ . '/db_operations.php';
require_once __DIR__ . '/telegram_helpers.php';
require_once __DIR__ . '/env_manager.php';

// Custom Debug Logging Function for lottery results
function write_lottery_debug_log($message) {
    $logFile = __DIR__ . '/lottery_debug.log';
    file_put_contents($logFile, date('[Y-m-d H:i:s]') . ' [LOTTERY_API] ' . $message . PHP_EOL, FILE_APPEND | LOCK_EX);
}

write_lottery_debug_log("------ get_lottery_results.php Entry Point ------");

// Load environment variables
load_env_file_simple(__DIR__ . '/.env');

write_lottery_debug_log("Attempting to get database connection.");
$pdo = get_db_connection();

if (is_array($pdo) && isset($pdo['db_error'])) {
    $errorMsg = "Database connection error: " . $pdo['db_error'];
    write_lottery_debug_log($errorMsg);
    http_response_code(503);
    echo json_encode(['status' => 'error', 'message' => $errorMsg]);
    exit;
}
write_lottery_debug_log("Database connection successful.");

try {
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
    
    // FINAL FIX: The incoming GET parameter is already correct UTF-8. No conversion is needed.
    // All previous attempts to convert encoding were incorrect and are now removed.
    $lotteryType = $_GET['lottery_type'] ?? null;

    write_lottery_debug_log("Received parameters: limit={$limit}, lotteryType='" . ($lotteryType ?? 'null') . "' (Using raw GET parameter).");

    $sql = "SELECT id, lottery_type, issue_number, winning_numbers, zodiac_signs, colors, drawing_date, created_at FROM lottery_results ";
    $params = [];

    if ($lotteryType) {
        $sql .= " WHERE lottery_type = ?";
        $params[] = $lotteryType;
    }

    $sql .= " ORDER BY drawing_date DESC, issue_number DESC LIMIT ?";
    $params[] = $limit;

    write_lottery_debug_log("Preparing SQL: '{$sql}' with params: " . json_encode($params, JSON_UNESCAPED_UNICODE));

    $stmt = $pdo->prepare($sql);
    if ($stmt === false) {
        $errorInfo = $pdo->errorInfo();
        $errorMsg = "SQL prepare failed: " . ($errorInfo[2] ?? "Unknown error");
        write_lottery_debug_log($errorMsg);
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Failed to prepare SQL query.', 'details' => $errorMsg]);
        exit;
    }
    write_lottery_debug_log("SQL prepared successfully.");

    $execSuccess = $stmt->execute($params);
    if ($execSuccess === false) {
        $errorInfo = $stmt->errorInfo();
        $errorMsg = "SQL execute failed: " . ($errorInfo[2] ?? "Unknown error");
        write_lottery_debug_log($errorMsg);
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Failed to execute SQL query.', 'details' => $errorMsg]);
        exit;
    }
    write_lottery_debug_log("SQL executed successfully.");

    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

    write_lottery_debug_log("Fetched " . count($results) . " results.");

    echo json_encode(['status' => 'success', 'lottery_results' => $results]);

} catch (PDOException $e) {
    $errorMsg = "PDOException in get_lottery_results.php: " . $e->getMessage();
    write_lottery_debug_log($errorMsg);
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'An internal database error occurred.']);
} catch (Throwable $e) {
    $errorMsg = "General error in get_lottery_results.php: " . $e->getMessage();
    write_lottery_debug_log($errorMsg);
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'An unexpected error occurred.']);
}

write_lottery_debug_log("------ get_lottery_results.php Exit Point ------");

?>