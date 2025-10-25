<?php
// backend/api/logout.php
require_once __DIR__ . '/../bootstrap.php';

// Start the session
session_start();

// Unset all session variables
$_SESSION = array();

// Destroy the session
session_destroy();

// Send a success response
http_response_code(200);
echo json_encode(["message" => "Logout successful"]);
