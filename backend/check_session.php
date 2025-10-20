<?php
// backend/check_session.php
// Checks if a user session is active and valid.

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/db_operations.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $sessionToken = $_COOKIE['session_token'] ?? '';

    if (empty($sessionToken)) {
        http_response_code(401);
        echo json_encode(['isLoggedIn' => false, 'error' => 'No session token provided.']);
        exit();
    }

    try {
        // Fetch the session from the database.
        $session = fetchOne($pdo, 
            "SELECT s.user_id, u.username, u.email 
             FROM sessions s 
             JOIN users u ON s.user_id = u.id 
             WHERE s.session_token = :session_token AND s.expires_at > NOW()",
            [':session_token' => $sessionToken]
        );

        if ($session) {
            http_response_code(200);
            echo json_encode(['isLoggedIn' => true, 'user' => ['id' => $session['user_id'], 'username' => $session['username'], 'email' => $session['email'] ]]);
        } else {
            // Session not found or expired.
            // Clear the invalid cookie.
            setcookie('session_token', '', [
                'expires' => time() - 3600,
                'path' => '/',
                'httponly' => true,
                'secure' => true,
                'samesite' => 'Lax'
            ]);
            http_response_code(401);
            echo json_encode(['isLoggedIn' => false, 'error' => 'Invalid or expired session.']);
        }

    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['isLoggedIn' => false, 'error' => 'Database error: ' . $e->getMessage()]);
    }
} else {
    http_response_code(405);
    echo json_encode(['error' => 'Invalid request method.']);
}
