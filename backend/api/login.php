<?php
// backend/api/login.php

ini_set('display_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json');
require_once __DIR__ . '/cors_headers.php';
require_once __DIR__ . '/../env_loader.php';
require_once __DIR__ . '/../db_connection.php';

$conn = null;
try {
    // Only allow POST requests for login
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

    $conn = get_db_connection();

    // Fetch user by email
    $stmt = $conn->prepare("SELECT id, password FROM users WHERE email = ?");
    if (!$stmt) {
        throw new Exception("Failed to prepare statement for user fetch: " . $conn->error);
    }
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows === 0) {
        http_response_code(401); // Unauthorized
        echo json_encode(['success' => false, 'message' => 'Invalid credentials.']);
        $stmt->close();
        exit;
    }

    $stmt->bind_result($user_id, $hashed_password);
    $stmt->fetch();
    $stmt->close();

    // Verify password
    if (password_verify($password, $hashed_password)) {
        // Login successful
        // In a real application, you would generate a JWT token here.
        // For this example, we'll return a simple success and user ID.
        
        // Generate a simple, short-lived token for demonstration (REPLACE WITH JWT IN PROD)
        $token_secret = getenv('ADMIN_SECRET'); // Reusing ADMIN_SECRET for simplicity, ideally a separate secret
        if (!$token_secret) {
            throw new Exception("Token secret is not set in .env file.");
        }
        $token_payload = [
            'user_id' => $user_id,
            'email' => $email,
            'exp' => time() + (3600 * 24) // Token valid for 24 hours
        ];
        // A very basic token generation (NOT JWT, use a library for production)
        $token = base64_encode(json_encode($token_payload) . '.' . hash_hmac('sha256', json_encode($token_payload), $token_secret));

        http_response_code(200); // OK
        echo json_encode(['success' => true, 'message' => 'Login successful.', 'token' => $token, 'user_id' => $user_id]);
    } else {
        http_response_code(401); // Unauthorized
        echo json_encode(['success' => false, 'message' => 'Invalid credentials.']);
    }

} catch (Exception $e) {
    error_log("Login API Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'An internal server error occurred.']);
} finally {
    if ($conn) {
        $conn->close();
    }
}
