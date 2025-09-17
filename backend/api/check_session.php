<?php
// backend/api/check_session.php

require_once __DIR__ . '/session_config.php';
require_once __DIR__ . '/config.php'; // Needed for TELEGRAM_SUPER_ADMIN_ID
session_start();

header('Content-Type: application/json');

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
