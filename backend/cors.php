<?php
// backend/cors.php

// --- CORS Configuration ---
// This file should be included at the very top of all your API entry points.

// 1. Specify the exact origin of your frontend application.
//    Using a wildcard (*) is possible but less secure.
$allowed_origin = 'https://ss.wenxiuxiu.eu.org';

// 2. Check if the request origin is the one we want to allow.
if (isset($_SERVER['HTTP_ORIGIN']) && $_SERVER['HTTP_ORIGIN'] == $allowed_origin) {
    header("Access-Control-Allow-Origin: " . $allowed_origin);
    header('Access-Control-Allow-Credentials: true');
    header('Access-Control-Allow-Headers: Origin, X-Requested-With, Content-Type, Accept, Authorization');
}

// 3. Handle pre-flight (OPTIONS) requests.
//    The browser sends this request first to check if the actual request is safe to send.
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
    header("Access-Control-Allow-Headers: Origin, X-Requested-With, Content-Type, Accept, Authorization");
    http_response_code(204); // No Content
    exit();
}
