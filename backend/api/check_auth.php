<?php
require_once __DIR__ . '/../src/config.php'; // Includes session_start()

if (isset($_SESSION['user_id']) && isset($_SESSION['email'])) {
    // User is authenticated
    Response::json([
        'isAuthenticated' => true,
        'user' => [
            'id' => $_SESSION['user_id'],
            'email' => $_SESSION['email']
        ]
    ]);
} else {
    // User is not authenticated
    Response::json(['isAuthenticated' => false]);
}
