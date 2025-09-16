<?php
// backend/api/register.php

require_once __DIR__ . '/database.php';

header('Content-Type: application/json');

// This script does not need CORS headers if it's only called by the proxy worker.
// However, the frontend will call it directly, so we need them.
// Let's assume for now that the proxy is not yet fully implemented for this new endpoint.
// I will remove these later when I refactor the backend to be fully proxied.
header("Access-Control-Allow-Origin: *"); // Loosened for now, will be removed later.
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit();
}

$response = ['success' => false, 'message' => 'An unknown error occurred.'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);

    $email = $input['email'] ?? null;
    $password = $input['password'] ?? null;

    // --- Validation ---
    if (empty($email) || empty($password)) {
        $response['message'] = 'Email and password are required.';
        http_response_code(400);
        echo json_encode($response);
        exit();
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $response['message'] = 'Invalid email format.';
        http_response_code(400);
        echo json_encode($response);
        exit();
    }

    if (strlen($password) < 8) {
        $response['message'] = 'Password must be at least 8 characters long.';
        http_response_code(400);
        echo json_encode($response);
        exit();
    }

    try {
        $pdo = getDbConnection();

        // --- Check if user already exists ---
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = :email");
        $stmt->execute([':email' => $email]);
        if ($stmt->fetch()) {
            $response['message'] = 'An account with this email already exists.';
            http_response_code(409); // Conflict
            echo json_encode($response);
            exit();
        }

        // --- Create new user ---
        $password_hash = password_hash($password, PASSWORD_DEFAULT);

        $stmt = $pdo->prepare("INSERT INTO users (email, password_hash) VALUES (:email, :password_hash)");
        $stmt->execute([
            ':email' => $email,
            ':password_hash' => $password_hash
        ]);

        $response = [
            'success' => true,
            'message' => 'User registered successfully.'
        ];
        http_response_code(201); // Created

    } catch (PDOException $e) {
        // In a real application, log the error message.
        $response['message'] = 'Database error during registration.';
        http_response_code(500);
    }

} else {
    $response['message'] = 'Only POST requests are accepted.';
    http_response_code(405); // Method Not Allowed
}

echo json_encode($response);
?>
