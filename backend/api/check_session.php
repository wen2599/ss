<?php
// backend/api/check_session.php

// This single header file handles CORS, session, config, and error handling
require_once __DIR__ . '/api_header.php';

// By the time we get here, the session has already been started by api_header.php

if (isset($_SESSION['user_id']) && isset($_SESSION['user_email'])) {
    // The TELEGRAM_SUPER_ADMIN_ID is available from config.php, included by api_header.php
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
