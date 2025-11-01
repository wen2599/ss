<?php
// backend/api/get_emails.php

// Enable error reporting for debugging
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Standard headers
header('Content-Type: application/json');
require_once __DIR__ . '/cors_headers.php';

// --- Session Handling ---
// Start the session to access logged-in user data
session_start();

// Security check: Ensure a user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401); // Unauthorized
    echo json_encode(['success' => false, 'message' => 'Unauthorized: You must be logged in to view emails.']);
    exit;
}

$user_id = $_SESSION['user_id'];

// Include database connection
require_once __DIR__ . '/../db_connection.php';

$conn = null;
try {
    $conn = get_db_connection();

    $emails = [];
    // --- Modified SQL Query ---
    // Selects emails associated with the logged-in user
    $sql = "SELECT id, from_address, subject, received_at FROM emails WHERE user_id = ? ORDER BY received_at DESC LIMIT 100";
    
    $stmt = $conn->prepare($sql);
    if ($stmt === false) {
        throw new Exception("Failed to prepare the SQL statement: " . $conn->error);
    }

    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result === false) {
        throw new Exception("Database query failed: " . $stmt->error);
    }

    if ($result->num_rows > 0) {
        while($row = $result->fetch_assoc()) {
            $emails[] = $row;
        }
    }

    $stmt->close();
    echo json_encode(['success' => true, 'data' => $emails]);

} catch (Exception $e) {
    error_log("API Error in get_emails.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'An internal server error occurred.']);

} finally {
    if ($conn) {
        $conn->close();
    }
}
