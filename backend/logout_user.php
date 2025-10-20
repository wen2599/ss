<?php
// backend/logout_user.php
// Handles user logout by destroying the session.

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/db_operations.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Check for session token in cookies
    $sessionToken = $_COOKIE['session_token'] ?? '';

    if (!empty($sessionToken)) {
        try {
            // Delete the session from the database
            delete($pdo, 'sessions', 'session_token = :session_token', [':session_token' => $sessionToken]);

            // Clear the session cookie
            setcookie('session_token', '', [
                'expires' => time() - 3600, // Set expiration to the past
                'path' => '/',
                'httponly' => true,
                'secure' => true,
                'samesite' => 'Lax'
            ]);

            http_response_code(200);
            echo json_encode(['message' => 'Logout successful.']);
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
        }
    } else {
        http_response_code(400);
        echo json_encode(['error' => 'No active session to log out.']);
    }
} else {
    http_response_code(405);
    echo json_encode(['error' => 'Invalid request method.']);
}
