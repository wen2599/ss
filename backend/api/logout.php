<?php
// backend/api/logout.php

// --- Session Configuration & Start ---
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

// --- CORS and HTTP Method Check ---
header("Access-Control-Allow-Origin: " . ($_ENV['FRONTEND_URL'] ?? 'http://localhost:5173'));
header("Access-Control-Allow-Credentials: true");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(204);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Only POST method is allowed.']);
    exit;
}

// --- Main Logic: Destroy the session ---

// 1. Unset all of the session variables
$_SESSION = [];

// 2. Expire the session cookie
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// 3. Finally, destroy the session
session_destroy();

// --- Success Response ---
http_response_code(200);
echo json_encode(['success' => true, 'message' => 'Logged out successfully.']);
?>
