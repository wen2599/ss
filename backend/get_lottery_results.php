<?php
// backend/get_lottery_results.php
// --- TEMPORARY DIAGNOSTIC SCRIPT ---

require_once __DIR__ . '/api_header.php';
require_once __DIR__ . '/db_operations.php';

$pdo = get_db_connection();
if (is_array($pdo) && isset($pdo['db_error'])) {
    http_response_code(503);
    echo json_encode(['status' => 'error', 'message' => 'Database connection failed.', 'details' => $pdo['db_error']]);
    exit;
}
if (!$pdo) {
    http_response_code(503);
    echo json_encode(['status' => 'error', 'message' => 'Database connection returned null.']);
    exit;
}

try {
    // This query will fetch all unique, non-empty lottery type names from the database.
    $sql = "SELECT DISTINCT lottery_type FROM lottery_results WHERE lottery_type IS NOT NULL AND lottery_type != '' ORDER BY lottery_type ASC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $results = $stmt->fetchAll(PDO::FETCH_COLUMN); // Fetch as a simple array of names

    // Return these names so we can see exactly what is stored in the database.
    echo json_encode([
        'status' => 'success_diagnostic',
        'message' => 'This is a list of unique lottery types found in your database.',
        'unique_lottery_types' => $results
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error_diagnostic',
        'message' => 'An error occurred while fetching unique lottery types.',
        'details' => $e->getMessage()
    ]);
}

?>