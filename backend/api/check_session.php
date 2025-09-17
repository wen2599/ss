<?php
// backend/api/check_session.php

require_once __DIR__ . '/session_config.php';
session_start();

header('Content-Type: application/json');
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Credentials: true");

if (isset($_SESSION['user_id']) && isset($_SESSION['user_email'])) {
    $response = [
        'success' => true,
        'loggedIn' => true,
        'user' => [
            'id' => $_SESSION['user_id'],
            'email' => $_SESSION['user_email']
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
