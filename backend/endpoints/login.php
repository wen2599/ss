<?php
// backend/endpoints/login.php

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../lib/helpers.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    send_json_response(['error' => 'Method not allowed.'], 405);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

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

// Use the global $db connection from bootstrap.php
global $db;
if (!$db) {
    send_json_response(['error' => 'Database connection failed.'], 500);
    exit;
}

// Query by email instead of username.
$stmt = $db->prepare("SELECT id, email, password FROM users WHERE email = ?");
if (!$stmt) {
    error_log("DB prepare statement failed: " . $db->error);
    send_json_response(['error' => 'Database query failed.'], 500);
    exit;
}

$stmt->bind_param("s", $email);
$stmt->execute();
$stmt->store_result();

if ($stmt->num_rows === 0) {
    send_json_response(['error' => 'Invalid credentials.'], 401);
    $stmt->close();
    $db->close();
    exit;
}

$user_id = null;
$user_email = null;
$hashed_password = null;
$stmt->bind_result($user_id, $user_email, $hashed_password);
$stmt->fetch();

if (password_verify($password, $hashed_password)) {
    $updateStmt = $db->prepare("UPDATE users SET last_login_time = CURRENT_TIMESTAMP WHERE id = ?");
    if ($updateStmt) {
        $updateStmt->bind_param("i", $user_id);
        $updateStmt->execute();
        $updateStmt->close();
    } else {
        error_log("Failed to prepare statement for updating last_login_time: " . $db->error);
    }

    session_regenerate_id(true);

    $_SESSION['loggedin'] = true;
    $_SESSION['user_id'] = $user_id;
    $_SESSION['email'] = $user_email;

    send_json_response([
        'success' => true,
        'user' => ['email' => $user_email]
    ]);
} else {
    send_json_response(['error' => 'Invalid credentials.'], 401);
}

$stmt->close();
$db->close();
?>