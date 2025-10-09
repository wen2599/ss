<?php

// API handler to provide the latest lottery number to the frontend from the database.

require_once __DIR__ . '/../core/Response.php';
require_once __DIR__ . '/../core/Database.php'; // Use the new database connection utility

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *'); // Allow all domains for simplicity

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
    // Log the actual error for debugging
    error_log("Database error in getLotteryNumber.php: " . $e->getMessage());

    // Send a generic error response to the client
    Response::json(['error' => 'Could not retrieve lottery data.'], 500);
}