<?php

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/jwt_helper.php';

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

$data = json_decode(file_get_contents("php://input"), true);

if (isset($data['email']) && isset($data['password'])) {
    $email = $data['email'];
    $password = $data['password'];

    global $db_connection;
    $stmt = $db_connection->prepare("SELECT id, password FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($user = $result->fetch_assoc()) {
        if (password_verify($password, $user['password'])) {
            $token = generate_jwt($user['id']);
            http_response_code(200);
            echo json_encode(["message" => "Login successful", "token" => $token]);
        } else {
            http_response_code(401);
            echo json_encode(["message" => "Invalid credentials"]);
        }
    } else {
        http_response_code(401);
        echo json_encode(["message" => "Invalid credentials"]);
    }
    $stmt->close();
    $db_connection->close();
} else {
    http_response_code(400);
    echo json_encode(["message" => "Email and password are required"]);
}
