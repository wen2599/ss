<?php
// backend/api/register.php

ini_set('display_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json');
require_once __DIR__ . '/cors_headers.php';
require_once __DIR__ . '/../env_loader.php'; // Ensure env_loader is always available
require_once __DIR__ . '/../db_connection.php';

$conn = null;
try {
    // Only allow POST requests for registration
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Method Not Allowed']);
        exit;
    }

    $data = json_decode(file_get_contents('php://input'), true);

    // Validate input
    if (json_last_error() !== JSON_ERROR_NONE || !isset($data['email'], $data['password'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid input. Email and password are required.']);
        exit;
    }

    $email = trim($data['email']);
    $password = $data['password'];

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid email format.']);
        exit;
    }

    if (strlen($password) < 6) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Password must be at least 6 characters long.']);
        exit;
    }

    $conn = get_db_connection();
    // IMPORTANT: Check if connection was successful
    if ($conn === null) {
        // get_db_connection() already logs the specific error internally
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Database connection failed during registration. Please try again later.']);
        exit;
    }

    // Check if email already exists
    $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
    if (!$stmt) {
        throw new Exception("Failed to prepare statement for email check: " . $conn->error);
    }
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $stmt->store_result();
    if ($stmt->num_rows > 0) {
        http_response_code(409); // Conflict
        echo json_encode(['success' => false, 'message' => 'Email already registered.']);
        $stmt->close();
        exit;
    }
    $stmt->close();

    // Hash password securely
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);

    // Insert new user
    $stmt = $conn->prepare("INSERT INTO users (email, password) VALUES (?, ?)");
    if (!$stmt) {
        throw new Exception("Failed to prepare statement for user insertion: " . $conn->error);
    }
    $stmt->bind_param("ss", $email, $hashed_password);

    if ($stmt->execute()) {
        http_response_code(201); // Created
        echo json_encode(['success' => true, 'message' => 'User registered successfully.']);
    } else {
        throw new Exception("Failed to execute user insertion: " . $stmt->error);
    }

    $stmt->close();

} catch (Exception $e) {
    error_log("Register API Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'An internal server error occurred.']);
} finally {
    if ($conn) {
        $conn->close();
    }
}
