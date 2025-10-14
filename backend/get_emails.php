<?php
// backend/get_emails.php

require_once __DIR__ . '/api_header.php';

// --- Enhanced Logging ---
$log_file = __DIR__ . '/../../backend.log';
function write_log($message) {
    global $log_file;
    // Prepend a timestamp to each log message
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($log_file, "[$timestamp] [get_emails] " . $message . "\n", FILE_APPEND);
}

write_log("--- Request received ---");

// --- Authentication Check ---
if (!isset($_SESSION['user_id'])) {
    write_log("Authentication failed: user_id not in session.");
    http_response_code(401); // Unauthorized
    echo json_encode(['status' => 'error', 'message' => 'You must be logged in to view emails.']);
    exit;
}

write_log("User authenticated. User ID: " . $_SESSION['user_id']);

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

    write_log("Query executed successfully. Found " . count($emails) . " emails.");

    // Return the emails as a JSON response
    http_response_code(200);
    echo json_encode(['status' => 'success', 'emails' => $emails]);
    write_log("--- Request finished successfully ---");

} catch (PDOException $e) {
    // Log the detailed error and return a generic error message
    $error_message = "Error fetching emails: " . $e->getMessage();
    write_log($error_message);
    error_log($error_message); // Also send to standard PHP error log

    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'An error occurred while fetching emails.']);
}
?>