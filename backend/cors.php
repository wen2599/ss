<?php
// backend/cors.php

// --- CORS Configuration ---
// This file should be included at the very top of all your API entry points.

// 1. Specify the exact origin of your frontend application.
//    Using a wildcard (*) is possible but less secure.
$allowed_origin = 'https://ss.wenxiuxiu.eu.org';

// 2. Check if the request origin is the one we want to allow.
//    If the origin matches, set the necessary CORS headers for actual requests.
if (isset($_SERVER['HTTP_ORIGIN']) && $_SERVER['HTTP_ORIGIN'] === $allowed_origin) {
    header("Access-Control-Allow-Origin: " . $allowed_origin);
    header('Access-Control-Allow-Credentials: true');
    header('Access-Control-Allow-Headers: Origin, X-Requested-With, Content-Type, Accept, Authorization');
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS'); // Allow all standard methods
}

// 3. Handle pre-flight (OPTIONS) requests.
//    The browser sends this request first to check if the actual request is safe to send.
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    // For OPTIONS requests, the headers must be set *before* responding with 204
    // The headers are already set in the block above if HTTP_ORIGIN matches.
    // If HTTP_ORIGIN does not match, then no CORS headers will be sent, and the browser will block.
    http_response_code(204); // No Content
    exit();
}
