<?php
// File: backend/api_header.php
if (defined('API_HEADER_LOADED')) return;

require_once __DIR__ . '/config.php';

header("Access-Control-Allow-Origin: " . (getenv('FRONTEND_URL') ?: '*'));
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS, DELETE, PUT');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

header('Content-Type: application/json');

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 86400, 'path' => '/',
        'domain' => '', 'secure' => true,
        'httponly' => true, 'samesite' => 'None'
    ]);
    session_start();
}

define('API_HEADER_LOADED', true);