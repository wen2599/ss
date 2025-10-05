<?php
// backend/endpoints/login.php

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    send_json_response(['error' => 'Method not allowed.'], 405);
}

$input = json_decode(file_get_contents('php://input'), true);

if (!$input || !isset($input['username']) || !isset($input['password'])) {
    send_json_response(['error' => 'Username and password are required.'], 400);
}

$username = $input['username'];
$password = $input['password'];

$conn = get_db_connection();
if (!$conn) {
    send_json_response(['error' => 'Database connection failed.'], 500);
}

// Assumes a 'users' table with 'username' and 'password' (hashed) columns.
$stmt = $conn->prepare("SELECT username, password FROM users WHERE username = ?");
if (!$stmt) {
    error_log("DB prepare statement failed: " . $conn->error);
    send_json_response(['error' => 'Database query failed.'], 500);
}

$stmt->bind_param("s", $username);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    send_json_response(['error' => 'Invalid username or password.'], 401);
}

$user = $result->fetch_assoc();

if (password_verify($password, $user['password'])) {
    // Passwords match. Start a session and log the user in.
    session_start();
    $_SESSION['loggedin'] = true;
    $_SESSION['username'] = $user['username'];

    send_json_response([
        'success' => true,
        'user' => ['username' => $user['username']]
    ]);
} else {
    // Passwords do not match.
    send_json_response(['error' => 'Invalid username or password.'], 401);
}

$stmt->close();
$conn->close();
?>