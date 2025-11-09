<?php
// File: backend/api_header.php (Simplified)

// 【关键修改】移除 require_once __DIR__ . '/config.php';
// index.php is now responsible for loading config.php before this file.

if (defined('API_HEADER_LOADED')) return;
define('API_HEADER_LOADED', true);

// Use the config() function which is guaranteed to be available.
header("Access-control-Allow-Origin: " . (config('FRONTEND_URL', '*')));
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