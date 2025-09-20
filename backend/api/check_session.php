<?php
// backend/api/check_session.php

// --- Session Configuration ---
$session_params = [
    'lifetime' => 86400,
    'path' => '/',
    'domain' => '',
    'secure' => isset($_SERVER['HTTPS']),
    'httponly' => true,
    'samesite' => 'Lax'
];
session_set_cookie_params($session_params);
session_start();

// --- CORS and Response Headers ---
header("Access-Control-Allow-Origin: " . ($_ENV['FRONTEND_URL'] ?? 'http://localhost:5173'));
header("Access-Control-Allow-Credentials: true");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(204);
    exit;
}

// --- Main Logic ---
if (isset($_SESSION['user_id']) && isset($_SESSION['user_email'])) {
    // User is logged in
    http_response_code(200);
    echo json_encode([
        'loggedIn' => true,
        'user' => [
            'id' => $_SESSION['user_id'],
            'email' => $_SESSION['user_email']
        ]
    ]);
} else {
    // User is not logged in
    http_response_code(200); // It's not an error, just a state check
    echo json_encode(['loggedIn' => false]);
}
?>
