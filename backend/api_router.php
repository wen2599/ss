<?php
// backend/api_router.php
// Version 2.1: Added explicit error logging to a file for better debugging.

// --- Pre-flight (OPTIONS) Request Handling ---
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    require_once __DIR__ . '/cors.php';
}

// --- Centralized Configuration & Setup ---
ini_set('display_errors', 0);
ini_set('log_errors', 1);
// Set a dedicated error log file within the backend directory
ini_set('error_log', __DIR__ . '/php_errors.log');
error_reporting(E_ALL);

// --- Headers & CORS ---
header('Content-Type: application/json');
require_once __DIR__ . '/cors.php';

// --- Database Connection ---
require_once __DIR__ . '/db_connection.php';
$conn = null;
try {
    $conn = get_db_connection();
} catch (Exception $e) {
    error_log("API Router DB Connection Failed: " . $e->getMessage());
    http_response_code(503);
    echo json_encode(['success' => false, 'message' => 'Service is temporarily unavailable.']);
    exit;
}

// --- Secure API Routing ---
$endpoint = $_GET['endpoint'] ?? '';
$action = $_GET['action'] ?? '';

$allowed_endpoints = [
    'auth' => __DIR__ . '/api/auth.php',
    'emails' => __DIR__ . '/api/get_emails.php',
    'email_details' => __DIR__ . '/api/get_email_details.php',
    'lottery' => __DIR__ . '/api/get_results.php'
];

// --- Route Dispatching ---
if (isset($allowed_endpoints[$endpoint])) {
    $target_file = $allowed_endpoints[$endpoint];
    require_once $target_file;
} else {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'API endpoint not found.']);
}

// --- Close Connection ---
if ($conn) {
    $conn->close();
}
