<?php
// backend/api/check_session.php

require_once __DIR__ . '/session_config.php';
require_once __DIR__ . '/config.php'; // Needed for TELEGRAM_SUPER_ADMIN_ID
session_start();

header('Content-Type: application/json');
// Allow requests from any origin
header("Access-Control-Allow-Origin: *");
// Allow credentials
header("Access-Control-Allow-Credentials: true");
// Specify allowed headers
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
// Specify allowed methods
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");

// Handle pre-flight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(204); // No Content
    exit;
}

if (isset($_SESSION['user_id']) && isset($_SESSION['user_email'])) {
    $is_superadmin = ($_SESSION['user_id'] == TELEGRAM_SUPER_ADMIN_ID);

    $response = [
        'success' => true,
        'loggedIn' => true,
        'user' => [
            'id' => $_SESSION['user_id'],
            'email' => $_SESSION['user_email'],
            'is_superadmin' => $is_superadmin
        ]
    ];
} else {
    $response = [
        'success' => true,
        'loggedIn' => false
    ];
}

echo json_encode($response);
?>
