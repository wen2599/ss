<?php
// backend/api/register.php

// This single header file handles CORS, session, config, error handling, and db connection
require_once __DIR__ . '/api_header.php';
require_once __DIR__ . '/database.php'; // For getDbConnection()

// We only accept POST requests for registration
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); // Method Not Allowed
    echo json_encode(['success' => false, 'message' => 'Only POST requests are accepted.']);
    exit;
}

// The global exception handler in api_header.php will catch any errors from this point on.

// Get input from the request body
$input = json_decode(file_get_contents('php://input'), true);

// Check if JSON decoding failed
if (json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid JSON format.']);
    exit;
}

$email = $input['email'] ?? null;
$password = $input['password'] ?? null;

// --- Validation ---
if (empty($email) || empty($password)) {
    http_response_code(400);
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

$pdo = getDbConnection();

// --- Check if user already exists ---
$stmt = $pdo->prepare("SELECT id FROM users WHERE email = :email");
$stmt->execute([':email' => $email]);
if ($stmt->fetch()) {
    http_response_code(409); // Conflict
    echo json_encode(['success' => false, 'message' => 'An account with this email already exists.']);
    exit;
}

// --- Create new user ---
$password_hash = password_hash($password, PASSWORD_DEFAULT);

$stmt = $pdo->prepare("INSERT INTO users (email, password_hash) VALUES (:email, :password_hash)");
$stmt->execute([
    ':email' => $email,
    ':password_hash' => $password_hash
]);

// --- Success ---
http_response_code(201); // Created
echo json_encode([
    'success' => true,
    'message' => 'User registered successfully.'
]);

?>
