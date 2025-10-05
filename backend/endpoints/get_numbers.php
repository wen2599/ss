<?php
// backend/endpoints/get_numbers.php

// Bootstrap the application
require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/helpers.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    send_json_response(['error' => 'Method not allowed.'], 405);
}

// 1. Get Database Connection
$conn = get_db_connection();
if (!$conn) {
    send_json_response(['error' => 'Database connection failed.'], 500);
}

// 2. Fetch the Latest Lottery Numbers
// This assumes a table named 'lottery_numbers' with columns 'issue' and 'numbers',
// and that we want the one with the most recent 'issue' date.
$result = $conn->query("SELECT issue, numbers FROM lottery_numbers ORDER BY issue DESC LIMIT 1");

if (!$result) {
    // Log the actual error for debugging, but send a generic message to the client.
    error_log("DB query failed: " . $conn->error);
    send_json_response(['error' => 'Could not fetch lottery data.'], 500);
}

$data = $result->fetch_assoc();

if (!$data) {
    // No records found
    send_json_response(['error' => 'No lottery data found.'], 404);
}

// The 'numbers' column is assumed to store a comma-separated string.
// We convert it to an array of integers for the JSON response.
$data['numbers'] = array_map('intval', explode(',', $data['numbers']));

$conn->close();

// 3. Send Response
send_json_response($data);
?>