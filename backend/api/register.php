<?php
require_once __DIR__ . '/../bootstrap.php';

$data = json_decode(file_get_contents("php://input"), true);

if (isset($data['email']) && isset($data['password'])) {
    $email = $data['email'];
    $password = $data['password'];

    // --- Basic Validation ---
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        http_response_code(400);
        echo json_encode(["message" => "Invalid email format"]);
        exit;
    }

    if (strlen($password) < 8) {
        http_response_code(400);
        echo json_encode(["message" => "Password must be at least 8 characters long"]);
        exit;
    }

    global $db_connection;

    // --- Check for existing user ---
    $stmt = $db_connection->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->fetch_assoc()) {
        http_response_code(409);
        echo json_encode(["message" => "User with this email already exists"]);
        $stmt->close();
        exit;
    }
    $stmt->close();

    // --- Create new user ---
    $hashed_password = password_hash($password, PASSWORD_BCRYPT);
    $stmt = $db_connection->prepare("INSERT INTO users (email, password) VALUES (?, ?)");
    $stmt->bind_param("ss", $email, $hashed_password);

    if ($stmt->execute()) {
        $user_id = $stmt->insert_id;
        $stmt->close();

        // --- Log in the new user ---
        session_regenerate_id(true);
        $_SESSION['user_id'] = $user_id;

        http_response_code(201);
        echo json_encode([
            "message" => "User created successfully",
            "user" => [
                "id" => $user_id,
                "email" => $email
            ]
        ]);
    } else {
        http_response_code(500);
        echo json_encode(["message" => "An unexpected error occurred."]);
    }

    $db_connection->close();
} else {
    http_response_code(400);
    echo json_encode(["message" => "Email and password are required"]);
}
