<?php
// backend/get_emails.php

require_once __DIR__ . '/api_header.php';

global $debug_info; // Access the global debug_info array

// Add get_emails.php specific debug info
$debug_info['get_emails_session_data'] = $_SESSION;
$debug_info['get_emails_session_id'] = session_id();

// --- Authentication Check ---
if (!isset($_SESSION['user_id'])) {
    http_response_code(401); // Unauthorized
    echo json_encode([
        'status' => 'error',
        'message' => 'You must be logged in to view emails.',
        'debug' => $debug_info // Include debug info in error response
    ]);
    exit;
}

$pdo = get_db_connection();

if (!$pdo) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Failed to connect to the database.',
        'debug' => $debug_info // Include debug info in error response
    ]);
    exit;
}

try {
    // Prepare and execute the query to fetch all emails from the 'emails' table
    $stmt = $pdo->prepare("SELECT id, sender, recipient, subject, html_content, created_at FROM emails ORDER BY created_at DESC");
    $stmt->execute();
    $emails = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Return the emails as a JSON response with debug info
    echo json_encode([
        'status' => 'success',
        'emails' => $emails,
        'debug' => $debug_info // Include debug info in success response
    ]);

} catch (PDOException $e) {
    // Log the error and return a generic error message
    // Using error_log here as a fallback, but primary debug will be in JSON response
    error_log("Error fetching emails: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'An error occurred while fetching emails.',
        'debug' => $debug_info // Include debug info in error response
    ]);
}
