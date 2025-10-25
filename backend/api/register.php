<?php
require_once __DIR__ . '/../bootstrap.php';

$data = json_decode(file_get_contents("php://input"), true);

if (isset($data['email']) && isset($data['password'])) {
    $email = $data['email'];
    $password = password_hash($data['password'], PASSWORD_BCRYPT);

    global $db_connection;
    $stmt = $db_connection->prepare("INSERT INTO users (email, password) VALUES (?, ?)");
    $stmt->bind_param("ss", $email, $password);

    if ($stmt->execute()) {
        http_response_code(201);
        echo json_encode(["message" => "User created successfully"]);
    } else {
        http_response_code(409); // Conflict, user likely already exists
        echo json_encode(["message" => "User with this email already exists"]);
    }
    $stmt->close();
    $db_connection->close();
} else {
    http_response_code(400);
    echo json_encode(["message" => "Email and password are required"]);
}
