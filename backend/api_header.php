<?php
// api_header.php

// Debugging array to collect info
$debug_info = [];

// Set session cookie parameters dynamically and securely before starting the session.
// This makes the application more portable and secure.
$cookieParams = [
    'lifetime' => 3600, // 1 hour
    'path' => '/',
    'secure' => true,   // Only send over HTTPS
    'httponly' => true, // Prevent JS access
    'samesite' => 'Lax' // CSRF protection
];

// Dynamically set the domain. For localhost, it should not be set.
// For production, it should be the bare domain (e.g., 'wenxiuxiu.eu.org').
$host = $_SERVER['HTTP_HOST'] ?? '';
if (!empty($host) && $host !== 'localhost' && !filter_var($host, FILTER_VALIDATE_IP)) {
    // Prepend a dot to make the cookie available to all subdomains.
    $cookieParams['domain'] = '.' . $host;
}

session_set_cookie_params($cookieParams);

// Start the session.
session_start();

// Collect debugging info
$debug_info['api_header_request_origin'] = $_SERVER['HTTP_ORIGIN'] ?? 'N/A';
$debug_info['api_header_session_id'] = session_id();
$debug_info['api_header_session_data'] = $_SESSION;

// --- CORS and Security Headers ---
$allowed_origins = ['http://localhost:3000', 'https://ss.wenxiuxiu.eu.org'];
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';

if (in_array($origin, $allowed_origins)) {
    header("Access-Control-Allow-Origin: " . $origin);
}

header("Access-Control-Allow-Credentials: true");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
header('Content-Type: application/json');

// Handle pre-flight OPTIONS requests from browsers.
// Check if REQUEST_METHOD is set before accessing it
if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Include the main configuration file which loads environment variables and other helpers.
require_once __DIR__ . '/config.php';
?>