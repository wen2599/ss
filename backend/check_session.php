<?php
require_once __DIR__ . '/bootstrap.php';

write_log("------ check_session.php Entry Point ------");

// Check if the user is logged in
if (isset($_SESSION['user_id'])) {
    write_log("Session active for user_id: " . $_SESSION['user_id']);
    json_response('success', [
        'isAuthenticated' => true,
        'user' => [
            'id' => $_SESSION['user_id'],
            'email' => $_SESSION['email'] ?? 'N/A' // Corrected to $_SESSION['email']
        ]
    ]);
} else {
    write_log("No active session found.");
    json_response('success', [
        'isAuthenticated' => false
    ]);
}

write_log("------ check_session.php Exit Point ------");

?>