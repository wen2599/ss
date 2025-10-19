<?php
require_once __DIR__ . '/api_header.php';

write_log("------ logout_user.php Entry Point ------");

$_SESSION = [];

if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

session_destroy();

write_log("User logged out successfully.");

http_response_code(200);
echo json_encode(['status' => 'success', 'message' => 'Logout successful!']);

write_log("------ logout_user.php Exit Point ------");

?>
