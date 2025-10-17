<?php
// backend/get_lottery_results.php

require_once __DIR__ . '/api_header.php';
require_once __DIR__ . '/db_operations.php';
require_once __DIR__ . '/telegram_helpers.php'; // Include telegram helpers
require_once __DIR__ . '/env_manager.php'; // Include env manager to load .env for TELEGRAM_BOT_TOKEN

// Custom Debug Logging Function for lottery results
function write_lottery_debug_log($message) {
    $logFile = __DIR__ . '/lottery_debug.log';
    file_put_contents($logFile, date('[Y-m-d H:i:s]') . ' [LOTTERY_API] ' . $message . PHP_EOL, FILE_APPEND | LOCK_EX);
}

write_lottery_debug_log("------ get_lottery_results.php Entry Point ------");

// Load environment variables
load_env_file_simple(__DIR__ . '/.env');

// --- Fetch Lottery Results ---
write_lottery_debug_log("Attempting to get database connection.");
$pdo = get_db_connection();

if (is_array($pdo) && isset($pdo['db_error'])) {
    $errorMsg = "Database connection is currently unavailable: " . $pdo['db_error'];
    write_lottery_debug_log($errorMsg);
    http_response_code(503);
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
write_lottery_debug_log("Database connection successful.");

try {
    // Fetch the latest lottery result(s)
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
    
    $rawLotteryType = $_GET['lottery_type'] ?? null;
    
    // Diagnostic: Log raw bytes of the incoming lotteryType
    if ($rawLotteryType !== null) {
        $hexDump = '';
        for ($i = 0; $i < strlen($rawLotteryType); $i++) {
            $hexDump .= sprintf("%02X ", ord($rawLotteryType[$i]));
        }
        write_lottery_debug_log("Diagnostic: Raw bytes of incoming lotteryType ('{$rawLotteryType}'): {$hexDump}");
    }

    // Attempt encoding conversion. We'll refine this based on the raw byte dump.
    // Keep the last attempt for now, but will likely change.
    $lotteryType = ($rawLotteryType !== null) ? mb_convert_encoding($rawLotteryType, 'UTF-8', 'Windows-1252') : null;

    write_lottery_debug_log("Received parameters: limit={$limit}, lotteryType='" . ($lotteryType ?? 'null') . "' (After encoding conversion from Windows-1252)");

    $sql = "SELECT id, lottery_type, issue_number, winning_numbers, zodiac_signs, colors, drawing_date, created_at FROM lottery_results ";
    $params = [];

    if ($lotteryType) {
        $sql .= " WHERE lottery_type = ?";
        $params[] = $lotteryType;
    }

    $sql .= " ORDER BY drawing_date DESC, issue_number DESC LIMIT ?";
    $params[] = $limit;

    write_lottery_debug_log("Preparing SQL: '{$sql}' with params: " . json_encode($params));

    $stmt = $pdo->prepare($sql);
    if ($stmt === false) {
        $errorInfo = $pdo->errorInfo();
        $errorMsg = "SQL prepare failed: " . ($errorInfo[2] ?? "Unknown error");
        error_log($errorMsg);
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
        error_log($errorMsg);
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
    $errorMsg = "PDOException in get_lottery_results.php: " . $e->getMessage() . "\nStack: " . $e->getTraceAsString();
    error_log($errorMsg);
    write_lottery_debug_log($errorMsg);
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'An internal database error occurred.', 'details' => $e->getMessage()]);
} catch (Throwable $e) {
    $errorMsg = "General error in get_lottery_results.php: " . $e->getMessage() . " on line " . $e->getLine() . " in " . $e->getFile() . "\nStack: " . $e->getTraceAsString();
    error_log($errorMsg);
    write_lottery_debug_log($errorMsg);
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'An unexpected error occurred.', 'details' => $e->getMessage()]);
}

write_lottery_debug_log("------ get_lottery_results.php Exit Point ------");

?>