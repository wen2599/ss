<?php
// backend/api/auth.php

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/AuthController.php';

global $db_connection;

$auth_controller = new AuthController($db_connection);

$request_uri = $_SERVER['REQUEST_URI'];
$data = json_decode(file_get_contents("php://input"), true);

if (strpos($request_uri, '/login') !== false) {
    $auth_controller->login($data);
} elseif (strpos($request_uri, '/register') !== false) {
    $auth_controller->register($data);
} elseif (strpos($request_uri, '/logout') !== false) {
    $auth_controller->logout();
} else {
    http_response_code(404);
    echo json_encode(["message" => "Endpoint not found"]);
}

$db_connection->close();
