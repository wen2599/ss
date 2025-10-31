<?php
// backend/api/register.php

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once __DIR__ . '/../db_connection.php';

function is_email_registered($email, $conn) {
    $sql = "SELECT COUNT(*) FROM users WHERE email = ?";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception("Prepare statement failed: " . $conn->error);
    }
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $stmt->bind_result($count);
    $stmt->fetch();
    $stmt->close();
    return $count > 0;
}

$conn = null;

try {
    $data = json_decode(file_get_contents("php://input"));

    if (!$data || !isset($data->email) || !isset($data->password)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid input. Email and password are required.']);
        exit();
    }

    $email = trim($data->email);
    $password = $data->password;

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid email format.']);
        exit();
    }

    if (strlen($password) < 6) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Password must be at least 6 characters long.']);
        exit();
    }

    $conn = get_db_connection();

    if (!$conn) {
        throw new Exception("Database connection failed during registration.");
    }

    if (is_email_registered($email, $conn)) {
        http_response_code(409); // 409 Conflict
        echo json_encode(['success' => false, 'message' => 'This email is already registered. Please try to log in.']);
        exit();
    }

    $hashed_password = password_hash($password, PASSWORD_DEFAULT);

    $sql = "INSERT INTO users (email, password_hash) VALUES (?, ?)";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception("Prepare statement for insert failed: " . $conn->error);
    }
    $stmt->bind_param("ss", $email, $hashed_password);
    
    if ($stmt->execute()) {
        http_response_code(201); // 201 Created
        echo json_encode(['success' => true, 'message' => 'Registration successful!']);
    } else {
        throw new Exception("Execute statement for insert failed: " . $stmt->error);
    }
    
    $stmt->close();

} catch (Exception $e) {
    error_log("Register API General Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'An internal server error occurred.']);
} finally {
    if ($conn) {
        $conn->close();
    }
}
