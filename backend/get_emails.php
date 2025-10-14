<?php
// backend/get_emails.php

// Include the main configuration file which handles environment variables and autoloading.
require_once __DIR__ . '/config.php';

header('Content-Type: application/json');

$pdo = get_db_connection();

if (!$pdo) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Failed to connect to the database.']);
    exit;
}

try {
    // Prepare and execute the query to fetch all emails from the 'emails' table
    $stmt = $pdo->prepare("SELECT id, sender, recipient, subject, html_content, created_at FROM emails ORDER BY created_at DESC");
    $stmt->execute();
    $emails = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Return the emails as a JSON response
    echo json_encode(['status' => 'success', 'emails' => $emails]);

} catch (PDOException $e) {
    // Log the error and return a generic error message
    error_log("Error fetching emails: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'An error occurred while fetching emails.']);
}
