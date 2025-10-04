<?php
session_start();
session_unset();
session_destroy();

header("Content-Type: application/json");
header("Access-Control-Allow-Credentials: true");
header("Access-Control-Allow-Origin: http://localhost:5173");

echo json_encode(['success' => 'Logged out successfully.']);
?>