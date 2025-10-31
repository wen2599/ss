<?php
// backend/receive_email.php

// Load environment variables
require_once __DIR__ . '/env_loader.php';
// Include the new database connection function
require_once __DIR__ . '/db_connection.php';

// --- Security Check ---
// The secret key must be sent from the Cloudflare Worker in this header.
$auth_header = $_SERVER['HTTP_X_WORKER_SECRET'] ?? '';
$expected_secret = getenv('WORKER_SECRET');

if (!$expected_secret || $auth_header !== $expected_secret) {
    http_response_code(403);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Forbidden: Invalid or missing secret token.']);
    exit;
}

// --- Request Validation ---
// This endpoint only accepts POST requests.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Method Not Allowed']);
    exit;
}

// --- Data Processing ---
// Get the raw POST data from the Worker.
$json_data = file_get_contents('php://input');
$data = json_decode($json_data, true);

// Check if JSON is valid and contains the required fields.
if (json_last_error() !== JSON_ERROR_NONE || !isset($data['from'], $data['to'])) {
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Bad Request: Invalid or incomplete JSON.']);
    exit;
}

// --- Database Interaction ---
$conn = null;
try {
    $conn = get_db_connection();

    // The `message_id` from the email header is the best unique identifier.
    // If it's not present, we create a fallback unique ID.
    $message_id = $data['message_id'] ?? uniqid('no-id-');
    $from_address = $data['from'];
    $subject = $data['subject'] ?? '';
    $body_text = $data['text'] ?? '';
    $body_html = $data['html'] ?? null; // Can be null if the email is plain text

    $stmt = $conn->prepare(
        "INSERT INTO emails (message_id, from_address, subject, body_text, body_html) 
         VALUES (?, ?, ?, ?, ?) 
         ON DUPLICATE KEY UPDATE 
            from_address=VALUES(from_address), 
            subject=VALUES(subject), 
            body_text=VALUES(body_text), 
            body_html=VALUES(body_html)"
    );

    if (!$stmt) {
        throw new Exception("Failed to prepare statement: " . $conn->error);
    }
    
    $stmt->bind_param("sssss", $message_id, $from_address, $subject, $body_text, $body_html);

    if ($stmt->execute()) {
        http_response_code(201); // 201 Created is appropriate for successful resource creation
        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'message' => 'Email processed and saved successfully.']);
    } else {
        throw new Exception("Failed to execute statement: " . $stmt->error);
    }

    $stmt->close();
    
} catch (Exception $e) {
    // Log the actual error to the server's error log for debugging
    error_log("Email Receiver Error: " . $e->getMessage());

    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Internal Server Error.']);

} finally {
    if ($conn) {
        $conn->close();
    }
}
