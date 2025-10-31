<?php
// backend/receive_email.php

// Load environment variables
require_once __DIR__ . '/env_loader.php';
// Include the new database connection function
require_once __DIR__ . '/db_connection.php';

// --- Security Check ---
$auth_header = $_SERVER['HTTP_X_WORKER_SECRET'] ?? '';
$expected_secret = getenv('WORKER_SECRET');

// --- DEBUGGING: Temporarily disable auth for easier testing ---
// if (!$expected_secret || $auth_header !== $expected_secret) {
//     http_response_code(403);
//     header('Content-Type: application/json');
//     echo json_encode(['success' => false, 'message' => 'Forbidden: Invalid or missing secret token.']);
//     exit;
// }

// --- Request Validation ---
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Method Not Allowed']);
    exit;
}

// --- Data Processing ---
$json_data = file_get_contents('php://input');
$data = json_decode($json_data, true);

if (json_last_error() !== JSON_ERROR_NONE || !isset($data['from'], $data['to'])) {
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Bad Request: Invalid or incomplete JSON.', 'received_data' => $json_data]);
    exit;
}

// --- Database Interaction ---
$conn = null;
try {
    $conn = get_db_connection();

    $message_id = $data['message_id'] ?? uniqid('no-id-');
    $from_address = $data['from'];
    $subject = $data['subject'] ?? '';
    $body_text = $data['text'] ?? '';
    $body_html = $data['html'] ?? null;
    $raw_content = $data['raw_content'] ?? null; // Extract raw content

    $stmt = $conn->prepare(
        "INSERT INTO emails (message_id, from_address, subject, body_text, body_html, raw_content) 
         VALUES (?, ?, ?, ?, ?, ?) 
         ON DUPLICATE KEY UPDATE 
            from_address=VALUES(from_address), 
            subject=VALUES(subject), 
            body_text=VALUES(body_text), 
            body_html=VALUES(body_html),
            raw_content=VALUES(raw_content)"
    );

    if (!$stmt) {
        throw new Exception("Failed to prepare statement: " . $conn->error);
    }
    
    $stmt->bind_param("ssssss", $message_id, $from_address, $subject, $body_text, $body_html, $raw_content);

    if ($stmt->execute()) {
        http_response_code(201);
        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'message' => 'Email processed and saved successfully.']);
    } else {
        throw new Exception("Failed to execute statement: " . $stmt->error);
    }

    $stmt->close();
    
} catch (Exception $e) {
    error_log("Email Receiver Error: " . $e->getMessage());

    // DEBUGGING: Return the specific database error message in the response body.
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false, 
        'message' => 'Internal Server Error. See details in error_details.',
        'error_details' => $e->getMessage() // This will contain the exact SQL error.
    ]);

} finally {
    if ($conn) {
        $conn->close();
    }
}
