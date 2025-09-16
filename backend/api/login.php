<?php
// backend/api/login.php

require_once __DIR__ . '/database.php';

header('Content-Type: application/json');
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header("Access-Control-Allow-Credentials: true"); // Important for sessions

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit();
}

// Start the session
session_start();

$response = ['success' => false, 'message' => 'An unknown error occurred.'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);

    $email = $input['email'] ?? null;
    $password = $input['password'] ?? null;

    if (empty($email) || empty($password)) {
        $response['message'] = 'Email and password are required.';
        http_response_code(400);
        echo json_encode($response);
        exit();
    }

    try {
        $pdo = getDbConnection();

        $stmt = $pdo->prepare("SELECT id, email, password_hash FROM users WHERE email = :email");
        $stmt->execute([':email' => $email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password_hash'])) {
            // Password is correct, so create a session
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_email'] = $user['email'];

            $response = [
                'success' => true,
                'message' => 'Login successful.',
                'user' => [
                    'id' => $user['id'],
                    'email' => $user['email']
                ]
            ];
        } else {
            // Bad credentials
            $response['message'] = 'Invalid email or password.';
            http_response_code(401); // Unauthorized
        }

    } catch (PDOException $e) {
        $response['message'] = 'Database error during login.';
        http_response_code(500);
    }

} else {
    $response['message'] = 'Only POST requests are accepted.';
    http_response_code(405); // Method Not Allowed
}

echo json_encode($response);
?>
