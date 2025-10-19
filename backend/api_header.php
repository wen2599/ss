<?php
// backend/api_header.php

// Define the allowed origin. Use a specific domain for production for better security.
$allowed_origins = [
    'https://ss.wenxiuxiu.eu.org', // Production frontend
    'http://localhost:5173',      // Local development
    'http://127.0.0.1:5173'       // Local development
];

if (isset($_SERVER['HTTP_ORIGIN']) && in_array($_SERVER['HTTP_ORIGIN'], $allowed_origins)) {
    header("Access-Control-Allow-Origin: {$_SERVER['HTTP_ORIGIN']}");
} else {
    // Optionally, you can fall back to a default or deny the request.
    // For this example, we deny by not sending the header.
}

// Set CORS headers to allow requests from the frontend domain.
// The 'Access-Control-Allow-Origin' header specifies which origins are allowed to access the resource.

// The 'Access-Control-Allow-Credentials' header is crucial for sending cookies (and session data) across domains.
// It tells the browser that the server allows the request to include user credentials.
header('Access-Control-Allow-Credentials: true');

// The 'Access-Control-Allow-Methods' header specifies the HTTP methods allowed for the resource.
header('Access-Control-Allow-Methods: GET, POST, OPTIONS, DELETE, PUT');

// The 'Access-Control-Allow-Headers' header specifies the HTTP headers that can be used during the actual request.
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

// Handle pre-flight OPTIONS requests.
// A pre-flight request is sent by the browser to check if the server understands CORS and is aware of the actual request's method and headers.
// If the request method is 'OPTIONS', we terminate the script here to prevent further processing.
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204); // No Content
    exit;
}

// Set a consistent JSON content type for all API responses.
header('Content-Type: application/json');

// Start or resume the session to access user authentication data.
// This must come after the CORS headers, especially in a cross-origin context.
if (session_status() === PHP_SESSION_NONE) {
    // Configure session cookie for cross-site contexts
    session_set_cookie_params([
        'lifetime' => 86400, // 24 hours
        'path' => '/',
        'domain' => '', // Set your domain in production, e.g., '.yourdomain.com'
        'secure' => true,   // Important for HTTPS
        'httponly' => true,
        'samesite' => 'None' // Crucial for cross-origin requests
    ]);
    session_start();
}

// Ensure errors are handled, and config is loaded.
require_once __DIR__ . '/config.php';
