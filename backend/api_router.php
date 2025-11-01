<?php
// backend/api_router.php

// Centralized error reporting
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Centralized session start
session_start();

// Centralized JSON header
header('Content-Type: application/json');

// Centralized DB connection
require_once __DIR__ . '/db_connection.php';

try {
    $conn = get_db_connection();
} catch (Exception $e) {
    http_response_code(503); // Service Unavailable
    echo json_encode(['success' => false, 'message' => 'Could not connect to the database.']);
    exit;
}

// Get the requested path from the query parameter
$requested_path = $_GET['path'] ?? '';

// Sanitize the path to prevent directory traversal attacks
$base_path = realpath(__DIR__);
// We expect paths like /api/auth.php, so we need to point it correctly inside the backend dir
$target_file = realpath($base_path . $requested_path);

// Security check: ensure the requested file is within the project's backend directory
if ($target_file === false || strpos($target_file, $base_path) !== 0) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'API endpoint not found or access denied.']);
    exit;
}

// Route to the specific API file if it exists
if (file_exists($target_file)) {
    // The included file will have access to the $conn variable
    require_once $target_file;
} else {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'API endpoint does not exist.']);
    exit;
}

// Close the connection after the script has run
if ($conn) {
    $conn->close();
}
