<?php
// api_header.php

// Start the session.
session_start();

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
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Include the main configuration file which loads environment variables and other helpers.
require_once __DIR__ . '/config.php';
?>