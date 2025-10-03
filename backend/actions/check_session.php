<?php
/**
 * Action: check_session
 *
 * This script checks if a user is currently authenticated by inspecting the session data.
 * It returns a JSON response indicating the authentication status and, if logged in,
 * essential user details.
 *
 * HTTP Method: GET
 *
 * Response:
 * - On success (authenticated): { "success": true, "isAuthenticated": true, "user": { "id": int, "email": string, "username": string } }
 * - On success (not authenticated): { "success": true, "isAuthenticated": false }
 */

// Note: init.php is already included by the main router (index.php), so session is started.

http_response_code(200);

if (isset($_SESSION['user_id']) && isset($_SESSION['user_email'])) {
    echo json_encode([
        'success' => true,
        'isAuthenticated' => true,
        'user' => [
            'id' => $_SESSION['user_id'],
            'email' => $_SESSION['user_email'],
            'username' => $_SESSION['username'] ?? null // Include username for display purposes
        ]
    ]);
} else {
    // The user is not logged in.
    echo json_encode([
        'success' => true,
        'isAuthenticated' => false
    ]);
}
?>