<?php
// backend/check_session.php

require_once __DIR__ . '/api_header.php'; // Ensures session handling is started

// Check if the user is logged in
if (isset($_SESSION['user_id'])) {
    // User is authenticated, return their data
    // You might want to fetch more user details from the database here
    echo json_encode([
        'status' => 'success',
        'isAuthenticated' => true,
        'user' => [
            'id' => $_SESSION['user_id'],
            'email' => $_SESSION['user_email'] ?? 'N/A' 
        ]
    ]);
} else {
    // User is not authenticated
    echo json_encode([
        'status' => 'success',
        'isAuthenticated' => false
    ]);
}
?>