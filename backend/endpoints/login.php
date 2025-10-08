<?php
// backend/endpoints/login.php

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../lib/helpers.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    send_json_response(['error' => 'Method not allowed.'], 405);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

// Expect 'email' from the frontend now.
if (!isset($input['email']) || !isset($input['password'])) {
    send_json_response(['error' => 'Email and password are required.'], 400);
    exit;
}

$email = trim($input['email']);
$password = $input['password'];

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    send_json_response(['error' => 'Invalid email format.'], 400);
    exit;
}

$conn = get_db_connection();
if (!$conn) {
    send_json_response(['error' => 'Database connection failed.'], 500);
    exit;
}

// Query by email instead of username.
$stmt = $conn->prepare("SELECT id, email, password FROM users WHERE email = ?");
if (!$stmt) {
    error_log("DB prepare statement failed: " . $conn->error);
    send_json_response(['error' => 'Database query failed.'], 500);
    exit;
}

$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    // User not found. Generic error to prevent email enumeration.
    send_json_response(['error' => 'Invalid credentials.'], 401);
    $stmt->close();
    $conn->close();
    exit;
}

$user = $result->fetch_assoc();

// Verify the provided password against the stored hash.
if (password_verify($password, $user['password'])) {
    // Update last login time
    $updateStmt = $conn->prepare("UPDATE users SET last_login_time = CURRENT_TIMESTAMP WHERE id = ?");
    if ($updateStmt) {
        $updateStmt->bind_param("i", $user['id']);
        $updateStmt->execute();
        $updateStmt->close();
    } else {
        // Log error, but don't block login
        error_log("Failed to prepare statement for updating last_login_time: " . $conn->error);
    }

    // Passwords match. Session is already started by index.php.
    // Regenerate session ID to prevent session fixation attacks.
    session_regenerate_id(true);

    $_SESSION['loggedin'] = true;
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['email'] = $user['email'];

    send_json_response([
        'success' => true,
        'user' => ['email' => $user['email']]
    ]);
} else {
    // Passwords do not match.
    send_json_response(['error' => 'Invalid credentials.'], 401);
}

$stmt->close();
$conn->close();
?>