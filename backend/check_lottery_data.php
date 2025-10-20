<?php
// backend/check_lottery_data.php

require_once __DIR__ . '/bootstrap.php'; // Load common functionalities including get_db_connection and write_log

// Set headers for clear text output in the browser
header('Content-Type: text/plain; charset=utf-8');

write_log("------ check_lottery_data.php Entry Point (Diagnostic) ------");
echo "--- Lottery Data Diagnostic Script ---\n\n";

try {
    echo "Attempting to connect to the database...\n";
    $pdo = get_db_connection();
    echo "Database connection successful.\n\n";

    echo "Querying the 'lottery_results' table for any 10 entries...\n";

    // A simple query to get a sample of data from the table
    $stmt = $pdo->query("SELECT * FROM lottery_results LIMIT 10");
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($results)) {
        echo "RESULT: The 'lottery_results' table is EMPTY or contains no data.\n\n";
        echo "This is the primary reason why no lottery numbers are displayed.\n";
        echo "Please check the process that is supposed to be populating this table with data.\n";
        write_log("Diagnostic Result: lottery_results table is empty.");
    } else {
        echo "RESULT: Found " . count($results) . " entries. The table is NOT empty.\n\n";
        echo "Here is a sample of the data:\n\n";
        print_r($results);
        echo "\n\nIf the 'lottery_type' values below do not exactly match '新澳门六合彩', '香港六合彩', or '老澳门六合彩', the query will not find them.\n";
        write_log("Diagnostic Result: Found " . count($results) . " entries. Sample data: " . json_encode($results[0]));
    }
} catch (PDOException $e) {
    echo "FATAL: A database error occurred!\n";
    echo "Error: " . $e->getMessage() . "\n";
    write_log("Diagnostic Failed: Database error - " . $e->getMessage());
}

echo "\n--- End of Diagnostic Script ---\n";
write_log("------ check_lottery_data.php Exit Point ------");

?>