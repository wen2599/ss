<?php
// backend/get_emails.php

require_once __DIR__ . '/api_header.php';

// --- Enhanced Logging ---
$log_file = __DIR__ . '/../../backend.log';
function write_log($message) {
    global $log_file;
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($log_file, "[$timestamp] [get_emails] " . $message . "\n", FILE_APPEND);
}

$session_id = session_id();
write_log("--- Request received. Session ID: {$session_id} ---");

// --- Authentication Check ---
if (!isset($_SESSION['user_id'])) {
    write_log("Authentication check failed: 'user_id' not found in session. Session data: " . json_encode($_SESSION));
    http_response_code(401); // Unauthorized
    echo json_encode(['status' => 'error', 'message' => 'You must be logged in to view emails.']);
    exit;
}

write_log("Authentication successful. User ID from session: " . $_SESSION['user_id']);

$pdo = get_db_connection();

if (!$pdo) {
    write_log("Database connection failed.");
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Failed to connect to the database.']);
    exit;
}

write_log("Database connection successful.");

try {
    // Prepare and execute the query to fetch all emails from the 'emails' table
    $stmt = $pdo->prepare("SELECT id, sender, recipient, subject, html_content, created_at FROM emails ORDER BY created_at DESC");
    $stmt->execute();
    $emails = $stmt->fetchAll(PDO::FETCH_ASSOC);

    write_log("Query executed. Found " . count($emails) . " emails.");

    // Return the emails as a JSON response
    echo json_encode(['status' => 'success', 'emails' => $emails]);

} catch (PDOException $e) {
    $error_message = "Error fetching emails: " . $e->getMessage();
    write_log($error_message);
    error_log($error_message);
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'An error occurred while fetching emails.']);
}
?>