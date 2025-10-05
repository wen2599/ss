<?php
// backend/endpoints/get_bills.php

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../config.php';

header('Content-Type: application/json');
$conn = get_db_connection();
if (!$conn) {
    http_response_code(500);
    echo json_encode(['error' => 'Database connection failed.']);
    exit;
}

// Determine if we are fetching the list or a single item
$email_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if ($email_id) {
    // --- Fetch a single email's full details ---
    
    // 1. Get the email itself
    $stmt_email = $conn->prepare("SELECT id, from_address, subject, body_html, received_at FROM emails WHERE id = ?");
    $stmt_email->bind_param('i', $email_id);
    $stmt_email->execute();
    $email_result = $stmt_email->get_result();
    $email_data = $email_result->fetch_assoc();
    $stmt_email->close();

    if (!$email_data) {
        http_response_code(404);
        echo json_encode(['error' => 'Email not found.']);
        exit;
    }

    // 2. Get the associated betting slips
    $stmt_slips = $conn->prepare("SELECT raw_text, parsed_data, is_valid FROM betting_slips WHERE email_id = ?");
    $stmt_slips->bind_param('i', $email_id);
    $stmt_slips->execute();
    $slips_result = $stmt_slips->get_result();
    $slips_data = $slips_result->fetch_all(MYSQLI_ASSOC);
    $stmt_slips->close();
    
    // Decode parsed_data JSON string into an object for each slip
    foreach ($slips_data as &$slip) {
        if ($slip['parsed_data']) {
            $slip['parsed_data'] = json_decode($slip['parsed_data']);
        }
    }

    $response = [
        'email' => $email_data,
        'slips' => $slips_data
    ];

    echo json_encode($response);

} else {
    // --- Fetch the list of all emails ---
    $result = $conn->query("SELECT id, from_address, subject, received_at FROM emails ORDER BY received_at DESC");
    if (!$result) {
        http_response_code(500);
        echo json_encode(['error' => 'Could not fetch email list.']);
        exit;
    }
    
    $emails = $result->fetch_all(MYSQLI_ASSOC);
    echo json_encode($emails);
}

$conn->close();
?>