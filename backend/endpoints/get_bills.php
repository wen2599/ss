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

$sql = "
    SELECT 
        e.id as email_id,
        e.subject,
        e.from_address,
        e.received_at,
        e.body_text AS raw_email_body,
        bs.id as betting_slip_id,
        bs.parsed_data,
        bs.is_valid,
        bs.processing_error
    FROM 
        emails e
    LEFT JOIN 
        betting_slips bs ON e.id = bs.email_id
    ORDER BY 
        e.received_at DESC;
";

$result = $conn->query($sql);

$bills = [];
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $bills[] = [
            'email_id' => $row['email_id'],
            'subject' => $row['subject'],
            'from_address' => $row['from_address'],
            'received_at' => $row['received_at'],
            'raw_email_body' => $row['raw_email_body'],
            'betting_slip_id' => $row['betting_slip_id'],
            'parsed_data' => json_decode($row['parsed_data'], true), // Decode JSON string to PHP array
            'is_valid' => (bool)$row['is_valid'],
            'processing_error' => $row['processing_error']
        ];
    }
}

$conn->close();
send_json_response($bills);

?>