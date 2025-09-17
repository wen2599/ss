<?php
// backend/api/logout.php

require_once __DIR__ . '/session_config.php';
session_start();
session_unset();
session_destroy();

header('Content-Type: application/json');
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Credentials: true");

echo json_encode(['success' => true, 'message' => 'Logout successful.']);
?>
