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

    $sql = "
        WITH RankedResults AS (
            SELECT
                id,
                lottery_type,
                issue_number,
                winning_numbers,
                zodiac_signs,
                colors,
                drawing_date,
                created_at,
                ROW_NUMBER() OVER(PARTITION BY lottery_type ORDER BY drawing_date DESC, issue_number DESC) as rn
            FROM
                lottery_results
        )
        SELECT
            id, lottery_type, issue_number, winning_numbers, zodiac_signs, colors, drawing_date, created_at
        FROM
            RankedResults
        WHERE
            rn = 1
        ORDER BY
            lottery_type;
    ";
    $params = [];

    write_lottery_debug_log("Preparing SQL: ". $sql . " with params: " . json_encode($params)); // ADDED LOG

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

    write_lottery_debug_log("Fetched " . count($results) . " raw results from DB."); // MODIFIED LOG

    // +++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++
    // +++ START OF THE FIX: Decode JSON strings into PHP arrays     +++
    // +++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++
    
    // Iterate over each result to decode the JSON fields
    $processedResults = array_map(function($row) {
        // Decode 'winning_numbers'. The second argument `true` decodes it into an associative array,
        // but since our JSON is a simple list, it becomes a numerically indexed array, which is what we want.
        $row['winning_numbers'] = json_decode($row['winning_numbers'], false); // `false` for array of strings
        $row['zodiac_signs'] = json_decode($row['zodiac_signs'], false);
        $row['colors'] = json_decode($row['colors'], false);

        // Add a safety check: if decoding fails, json_decode returns null.
        // We should turn it into an empty array to prevent frontend errors.
        if (!is_array($row['winning_numbers'])) $row['winning_numbers'] = [];
        if (!is_array($row['zodiac_signs']))    $row['zodiac_signs'] = [];
        if (!is_array($row['colors']))          $row['colors'] = [];
        
        return $row;
    }, $results);

    write_lottery_debug_log("Processed " . count($processedResults) . " results (decoded JSON fields).");

    // +++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++
    // +++ END OF THE FIX                                            +++
    // +++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++

    // Now, encode the processed results, where the fields are proper arrays
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