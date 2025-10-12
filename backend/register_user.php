<?php

require_once __DIR__ . '/config.php'; // Use the central config file

// Set headers for CORS and JSON response
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *'); // For development only. Restrict in production.
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle browser preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit;
}

// --- Input Reception and Validation ---
$data = json_decode(file_get_contents('php://input'), true);

if (!$data) {
    http_response_code(400); // Bad Request
    echo json_encode(['error' => 'Invalid JSON received.']);
    exit;
}

$email = $data['email'] ?? null;
$username = $data['username'] ?? null;
$password = $data['password'] ?? null;

if (empty($email) || empty($username) || empty($password)) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing required fields: email, username, and password.']);
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid email format.']);
    exit;
}

// --- Database Interaction ---
$pdo = get_db_connection();
if (!$pdo) {
    http_response_code(503); // Service Unavailable
    echo json_encode(['error' => 'Database connection is currently unavailable.']);
    exit;
}

// Hash the password for secure storage
$password_hash = password_hash($password, PASSWORD_DEFAULT);

try {
    // 1. Check if username or email already exists
    $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
    $stmt->execute([$username, $email]);
    if ($stmt->fetch()) {
        http_response_code(409); // Conflict
        echo json_encode(['error' => 'A user with this username or email already exists.']);
        exit;
    }

    // 2. Insert the new user into the database
    $stmt = $pdo->prepare(
        "INSERT INTO users (username, email, password_hash, created_at) VALUES (?, ?, ?, NOW())"
    );
    
    $isSuccess = $stmt->execute([$username, $email, $password_hash]);

    if ($isSuccess) {
        http_response_code(201); // Created
        echo json_encode(['message' => 'User registered successfully.']);
    } else {
        http_response_code(500); // Internal Server Error
        echo json_encode(['error' => 'Failed to register the user due to a server issue.']);
    }

} catch (PDOException $e) {
    error_log("User registration error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'An internal database error occurred.']);
}

?>