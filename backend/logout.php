<?php
session_start();

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(204);
    exit;
}

// Unset all of the session variables
$_SESSION = array();

// Destroy the session.
session_destroy();

header('Content-Type: application/json');
echo json_encode(['success' => true, 'message' => 'Logged out successfully.']);
?>
