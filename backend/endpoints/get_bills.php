<?php
// backend/endpoints/get_bills.php

session_start();

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../lib/helpers.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    send_json_response(['error' => 'Method not allowed.'], 405);
    exit;
}

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    send_json_response(['error' => 'Unauthorized'], 401);
    exit;
}

$conn = get_db_connection();
if (!$conn) {
    send_json_response(['error' => 'Database connection failed.'], 500);
    exit;
}

// The database schema has been updated. The new 'bills' table consolidates
// information previously stored in 'emails' and 'betting_slips'. This query
// now targets the 'bills' table and adapts its structure for the frontend.
$sql = "
    SELECT 
        id,
        bill_id,
        sender,
        total_amount,
        details,
        received_at
    FROM 
        bills
    ORDER BY 
        received_at DESC;
";

$result = $conn->query($sql);

$bills = [];
if ($result) {
    while ($row = $result->fetch_assoc()) {
        // Adapt the data from the 'bills' table to the structure expected by BillsPage.jsx
        $bills[] = [
            // Mapping new columns to the old structure
            'email_id' => $row['id'], // Use the primary key of the bills table
            'subject' => "Bill from {$row['sender']} - {$row['bill_id']}", // Generate a descriptive subject
            'from_address' => $row['sender'],
            'received_at' => $row['received_at'],
            'raw_email_body' => 'Raw email body is not stored in the new schema.', // Placeholder for removed field

            // Betting slip related data
            'betting_slip_id' => $row['bill_id'], // Use the unique bill_id
            'parsed_data' => json_decode($row['details'], true), // 'details' now holds the parsed data
            'is_valid' => isset($row['total_amount']) && $row['total_amount'] > 0, // Determine validity based on total_amount
            'processing_error' => null // 'processing_error' field is no longer tracked in this schema
        ];
    }
}

$conn->close();
send_json_response($bills);

?>