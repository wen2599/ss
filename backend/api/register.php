<?php
// backend/api/register.php

require_once __DIR__ . '/database.php';

// --- CORS and HTTP Method Check ---
// Allow requests from the frontend
header("Access-Control-Allow-Origin: " . ($_ENV['FRONTEND_URL'] ?? 'http://localhost:5173'));
header("Access-Control-Allow-Credentials: true");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header('Content-Type: application/json');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(204); // No Content
    exit;
}

// Ensure the request method is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); // Method Not Allowed
    echo json_encode(['success' => false, 'message' => 'Only POST method is allowed.']);
    exit;
}

// --- Main Logic ---
$db = null;
try {
    // Get input data from JSON body
    $data = json_decode(file_get_contents('php://input'), true);
    $email = $data['email'] ?? null;
    $password = $data['password'] ?? null;

    // --- 1. Input Validation ---
    if (empty($email) || empty($password)) {
        http_response_code(400); // Bad Request
        echo json_encode(['success' => false, 'message' => 'Email and password are required.']);
        exit;
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid email format.']);
        exit;
    }
    if (strlen($password) < 8) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Password must be at least 8 characters long.']);
        exit;
    }

    $db = getDbConnection();

    // --- 2. Check for Existing User ---
    $stmt = $db->prepare("SELECT id FROM users WHERE email = :email");
    $stmt->execute([':email' => $email]);
    if ($stmt->fetch()) {
        http_response_code(409); // Conflict
        echo json_encode(['success' => false, 'message' => 'An account with this email already exists.']);
        exit;
    }

    // --- 3. Hash Password and Insert User ---
    $password_hash = password_hash($password, PASSWORD_DEFAULT);

    $stmt = $db->prepare("INSERT INTO users (email, password_hash) VALUES (:email, :password_hash)");
    $stmt->execute([':email' => $email, ':password_hash' => $password_hash]);

    // --- 4. Success Response ---
    http_response_code(201); // Created
    echo json_encode(['success' => true, 'message' => 'User registered successfully.']);

} catch (Exception $e) {
    // --- 5. Generic Error Handling ---
    http_response_code(500); // Internal Server Error
    // In a real app, log the detailed error, don't expose it to the client.
    echo json_encode(['success' => false, 'message' => 'An internal server error occurred.', 'error' => $e->getMessage()]);
} finally {
    // Close the database connection
    if ($db) {
        $db = null;
    }
}
?>
