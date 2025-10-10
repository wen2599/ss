<?php

// API handler to provide the latest lottery number to the frontend from the database.
// Core dependencies are now loaded by the main index.php router.

// Security Check: Ensure this script is loaded by index.php, not accessed directly.
if (!defined('DB_HOST')) {
    die('Direct access not permitted');
}

try {
    $conn = getDbConnection();

    // Query to get the most recent lottery number entry
    $sql = "SELECT issue_number, winning_numbers, drawing_date, created_at
            FROM lottery_numbers
            ORDER BY id DESC
            LIMIT 1";

    $result = $conn->query($sql);

    if ($result && $result->num_rows > 0) {
        // Fetch the data
        $row = $result->fetch_assoc();
        Response::json($row);
    } else {
        // If no records are found, return a placeholder response
        Response::json([
            'issue_number' => 'N/A',
            'winning_numbers' => '等待开奖',
            'drawing_date' => null,
            'created_at' => null
        ], 404);
    }

    $conn->close();

} catch (Exception $e) {
    // The global exception handler in index.php will catch this
    // and return a formatted JSON error.
    throw new Exception('Could not retrieve lottery data: ' . $e->getMessage());
}