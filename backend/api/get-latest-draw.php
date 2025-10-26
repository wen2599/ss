<?php
// backend/api/get-latest-draw.php

// --- CORS Configuration ---
// This is crucial for allowing your frontend to fetch the data.
header("Access-Control-Allow-Origin: https://ss.wenxiuxiu.eu.org");
header("Content-Type: application/json; charset=UTF-8");

// Bootstrap the application to get the database connection
require_once __DIR__ . '/../bootstrap.php';

// --- Main Logic ---
global $db_connection;

// Query to get the latest lottery draw record.
// Ordering by 'id DESC' is the most reliable way to get the most recently added record.
$query = "SELECT draw_date, lottery_type, draw_period, numbers, created_at FROM lottery_draws ORDER BY id DESC LIMIT 1";

$response = [];

try {
    if ($result = $db_connection->query($query)) {
        if ($row = $result->fetch_assoc()) {
            // If a record is found, prepare it for the response.
            http_response_code(200);
            $response = [
                'status' => 'success',
                'data' => [
                    'draw_date' => $row['draw_date'],
                    'lottery_type' => $row['lottery_type'], // Added lottery_type
                    'draw_period' => $row['draw_period'],
                    'numbers' => $row['numbers'],
                    'recorded_at' => $row['created_at']
                ]
            ];
        } else {
            // If no records are in the database yet.
            http_response_code(404);
            $response = [
                'status' => 'error',
                'message' => 'No lottery records found.'
            ];
        }
        $result->free();
    } else {
        // If the query itself fails.
        http_response_code(500);
        $response = [
            'status' => 'error',
            'message' => 'Database query failed.'
        ];
        // Log the detailed error on the server for debugging.
        error_log("DB Error in get-latest-draw.php: " . $db_connection->error);
    }
} catch (Exception $e) {
    http_response_code(500);
    $response = [
        'status' => 'error',
        'message' => 'An unexpected server error occurred.'
    ];
    error_log("Exception in get-latest-draw.php: " . $e->getMessage());
}

// Close the database connection
$db_connection->close();

// Echo the final JSON response
echo json_encode($response);

?>