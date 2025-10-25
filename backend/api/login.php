<?php
require_once __DIR__ . '/../bootstrap.php';

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
            // Generate JWT token
            $token = create_jwt($user['id']);
            http_response_code(200);
            echo json_encode(["token" => $token]);
        } else {
            http_response_code(401);
            echo json_encode(["message" => "Invalid credentials"]);
        }
    } else {
        http_response_code(404);
        echo json_encode(["message" => "User not found"]);
    }
    $stmt->close();
    $db_connection->close();
} else {
    http_response_code(400);
    echo json_encode(["message" => "Email and password are required"]);
}
