<?php
// backend/api/login.php

// This single header file handles CORS, session, config, error handling, and db connection
require_once __DIR__ . '/api_header.php';
require_once __DIR__ . '/database.php'; // For getDbConnection()

// We only accept POST requests for login
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); // Method Not Allowed
    echo json_encode(['success' => false, 'message' => 'Only POST requests are accepted.']);
    exit;
}

// The global exception handler in api_header.php will catch any errors from this point on.

$input = json_decode(file_get_contents('php://input'), true);

// Check if JSON decoding failed
if (json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid JSON format.']);
    exit;
}

$email = $input['email'] ?? null;
$password = $input['password'] ?? null;

if (empty($email) || empty($password)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Email and password are required.']);
    exit;
}

$pdo = getDbConnection();

$stmt = $pdo->prepare("SELECT id, email, password_hash FROM users WHERE email = :email");
$stmt->execute([':email' => $email]);
$user = $stmt->fetch();

if ($user && password_verify($password, $user['password_hash'])) {
    // Password is correct, so create a session.
    // session_regenerate_id() is a good practice to prevent session fixation attacks.
    session_regenerate_id(true);

    $_SESSION['user_id'] = $user['id'];
    $_SESSION['user_email'] = $user['email'];

    $is_superadmin = ($_SESSION['user_id'] == TELEGRAM_SUPER_ADMIN_ID);

    $response = [
        'success' => true,
        'message' => 'Login successful.',
        'user' => [
            'id' => $user['id'],
            'email' => $user['email'],
            'is_superadmin' => $is_superadmin
        ]
    ];

    http_response_code(200); // OK
    echo json_encode($response);

} else {
    // Bad credentials
    http_response_code(401); // Unauthorized
    echo json_encode(['success' => false, 'message' => 'Invalid email or password.']);
}

?>
