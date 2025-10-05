<?php
// backend/endpoints/email_receiver.php

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../config.php';

// --- Helper: Bet Slip Parser ---
// This function attempts to parse a single line of text into a structured bet.
// Format expected: [Bet Type] [Content] [Amount]
// Example: "特码 49 100" -> {type: "特码", content: "49", amount: 100}
// Example: "三中二 1,2,3 100" -> {type: "三中二", content: "1,2,3", amount: 100}
function parse_betting_slip($line) {
    $parts = preg_split('/\s+/', trim($line), 3);

    if (count($parts) < 3) {
        return ['is_valid' => false, 'data' => null];
    }

    list($type, $content, $amount) = $parts;

    // Validate amount is a number
    if (!is_numeric($amount)) {
        return ['is_valid' => false, 'data' => null];
    }

    $parsed_data = [
        'type' => $type,
        'content' => $content,
        'amount' => floatval($amount),
    ];

    return ['is_valid' => true, 'data' => $parsed_data];
}

// --- Main Logic ---
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); // Method Not Allowed
    exit;
}

// Authenticate the worker request
$auth_header = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
if ($auth_header !== 'Bearer ' . WORKER_SECRET) {
    http_response_code(403); // Forbidden
    error_log('Email receiver: Invalid or missing authorization token.');
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
if (!isset($input['from'], $input['subject'], $input['body_text'])) {
    http_response_code(400); // Bad Request
    error_log('Email receiver: Missing required fields in POST body.');
    exit;
}

$conn = get_db_connection();
if (!$conn) {
    http_response_code(500); // Server Error
    error_log("Email receiver: Database connection failed.");
    exit;
}

// Use a transaction to ensure all-or-nothing insertion
$conn->begin_transaction();

try {
    // 1. Insert the raw email
    $stmt_email = $conn->prepare(
        "INSERT INTO emails (message_id, from_address, subject, body_html, body_text) VALUES (?, ?, ?, ?, ?)"
    );
    $message_id = $input['message_id'] ?? uniqid();
    $stmt_email->bind_param('sssss', $message_id, $input['from'], $input['subject'], $input['body_html'], $input['body_text']);
    $stmt_email->execute();
    $email_id = $conn->insert_id;
    $stmt_email->close();

    // 2. Parse the text body and insert betting slips
    $lines = explode("\n", $input['body_text']);
    $stmt_slip = $conn->prepare(
        "INSERT INTO betting_slips (email_id, raw_text, parsed_data, is_valid) VALUES (?, ?, ?, ?)"
    );

    foreach ($lines as $line) {
        if (empty(trim($line))) continue;

        $result = parse_betting_slip($line);
        $is_valid_int = $result['is_valid'] ? 1 : 0;
        $parsed_json = $result['is_valid'] ? json_encode($result['data']) : null;

        $stmt_slip->bind_param('issi', $email_id, $line, $parsed_json, $is_valid_int);
        $stmt_slip->execute();
    }
    
    $stmt_slip->close();

    // If everything was successful, commit the transaction
    $conn->commit();
    http_response_code(201); // Created
    echo json_encode(['status' => 'success', 'email_id' => $email_id]);

} catch (Exception $e) {
    // If anything fails, roll back the transaction
    $conn->rollback();
    http_response_code(500);
    error_log("Email receiver transaction failed: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}

$conn->close();
?>