<?php
/**
 * get_lottery_results.php (Refactored for Correct Inclusion)
 *
 * This script retrieves lottery results. It now only includes the main API header,
 * which is responsible for loading the entire application configuration and all
 * necessary dependencies like db_operations.php. This prevents duplicate inclusions.
 */

require_once __DIR__ . '/api_header.php';

// The db_operations.php file is now loaded via config.php, which is included in api_header.php.
// No further require_once statements are needed.

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
write_lottery_debug_log("Database connection successful.");

try {
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
    $lotteryType = isset($_GET['lottery_type']) ? urldecode($_GET['lottery_type']) : null;

    write_lottery_debug_log("Received parameters: limit={$limit}, lotteryType='{$lotteryType}'");

    $sql = "SELECT id, lottery_type, issue_number, winning_numbers, zodiac_signs, colors, drawing_date, created_at FROM lottery_results ";
    $params = [];

    if ($lotteryType) {
        $sql .= " WHERE lottery_type = ?";
        $params[] = $lotteryType;
    }

    $sql .= " ORDER BY drawing_date DESC, issue_number DESC LIMIT ?";
    $params[] = $limit;

    write_lottery_debug_log("Preparing SQL: ". $sql . " with params: " . json_encode($params));

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

    write_lottery_debug_log("Fetched " . count($results) . " raw results from DB.");

    $processedResults = array_map(function($row) {
        $row['winning_numbers'] = json_decode($row['winning_numbers'], false);
        $row['zodiac_signs'] = json_decode($row['zodiac_signs'], false);
        $row['colors'] = json_decode($row['colors'], false);

        if (!is_array($row['winning_numbers'])) $row['winning_numbers'] = [];
        if (!is_array($row['zodiac_signs']))    $row['zodiac_signs'] = [];
        if (!is_array($row['colors']))          $row['colors'] = [];
        
        return $row;
    }, $results);

    write_lottery_debug_log("Processed " . count($processedResults) . " results (decoded JSON fields).");

    echo json_encode(['status' => 'success', 'lottery_results' => $processedResults]);

} catch (PDOException $e) {
    $errorMsg = "Error fetching lottery results: " . $e->getMessage();
    error_log($errorMsg);
    write_lottery_debug_log($errorMsg);
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'An error occurred while fetching lottery results.']);
}

write_lottery_debug_log("------ get_lottery_results.php Exit Point ------");

?>
