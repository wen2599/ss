<?php
// File: backend/api_header.php

if (defined('API_HEADER_LOADED')) return;
define('API_HEADER_LOADED', true);

// 使用 config() 函数，并提供一个安全的备用值（尽管我们期望它能读到正确的URL）
$frontend_url = config('FRONTEND_URL');

// 如果 frontend_url 为空或未设置，则不发送任何 CORS 头，以便在浏览器中看到更明确的错误
if ($frontend_url) {
    header("Access-Control-Allow-Origin: " . $frontend_url);
} else {
    // 故意不设置，这样浏览器会报一个关于缺少头的错误，而不是关于 '*' 的错误
    // 这能帮助我们确认问题就是 FRONTEND_URL 没读到
}

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