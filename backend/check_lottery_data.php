<?php
// backend/check_lottery_data.php

// Set headers for clear text output in the browser
header('Content-Type: text/plain; charset=utf-8');

echo "--- Lottery Data Diagnostic Script ---\n\n";

// Include necessary files
require_once __DIR__ . '/api_header.php';
require_once __DIR__ . '/db_operations.php';

echo "Attempting to connect to the database...\n";

$pdo = get_db_connection();

if (is_array($pdo) && isset($pdo['db_error'])) {
    echo "FATAL: Database connection failed!\n";
    echo "Error: " . $pdo['db_error'] . "\n";
    exit;
}
if (!$pdo) {
    echo "FATAL: Database connection failed! (Returned null)\n";
    exit;
}

echo "Database connection successful.\n\n";
echo "Querying the 'lottery_numbers' table for any 10 entries...\n";

try {
    // A simple query to get a sample of data from the table
    $stmt = $pdo->query("SELECT * FROM lottery_numbers LIMIT 10");
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($results)) {
        echo "RESULT: The 'lottery_numbers' table is EMPTY or contains no data.\n\n";
        echo "This is the primary reason why no lottery numbers are displayed.\n";
        echo "Please check the process that is supposed to be populating this table with data.\n";
    } else {
        echo "RESULT: Found " . count($results) . " entries. The table is NOT empty.\n\n";
        echo "Here is a sample of the data:\n\n";
        print_r($results);
        echo "\n\nIf the 'lottery_type' values below do not exactly match '新澳门六合彩', '香港六合彩', or '老澳门六合彩', the query will not find them.\n";
    }
} catch (PDOException $e) {
    echo "ERROR: An exception occurred while querying the database.\n";
    echo "Message: " . $e->getMessage() . "\n";
}

echo "\n--- End of Diagnostic Script ---\n";

?>
