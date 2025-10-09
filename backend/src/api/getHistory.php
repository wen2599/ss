<?php

// API handler to provide all historical lottery numbers to the frontend.
// Core dependencies are now loaded by the main index.php router.

try {
    $conn = getDbConnection();

    // Query to get all lottery number entries, ordered from newest to oldest
    $sql = "SELECT id, issue_number, winning_numbers, drawing_date, created_at
            FROM lottery_numbers
            ORDER BY id DESC";

    $result = $conn->query($sql);

    $history = [];
    if ($result) {
        // Fetch all rows into an associative array
        while ($row = $result->fetch_assoc()) {
            $history[] = $row;
        }
    }

    $conn->close();

    Response::json($history);

} catch (Exception $e) {
    // Log the actual error for debugging
    error_log("Database error in getHistory.php: " . $e->getMessage());

    // Send a generic error response to the client
    Response::json(['error' => 'Could not retrieve lottery history.'], 500);
}