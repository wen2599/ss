<?php
// backend/endpoints/register.php

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    send_json_response(['error' => 'Method not allowed.'], 405);
}

$input = json_decode(file_get_contents('php://input'), true);

if (!$input || !isset($input['username']) || !isset($input['password'])) {
    send_json_response(['error' => 'Username and password are required.'], 400);
}

$username = trim($input['username']);
$password = $input['password'];

if (empty($username) || empty($password)) {
    send_json_response(['error' => 'Username and password cannot be empty.'], 400);
}

$conn = get_db_connection();
if (!$conn) {
    send_json_response(['error' => 'Database connection failed.'], 500);
}

// First, check if the username already exists.
$stmt_check = $conn->prepare("SELECT username FROM users WHERE username = ?");
if (!$stmt_check) {
    error_log("DB prepare statement failed (check): " . $conn->error);
    send_json_response(['error' => 'Database query failed.'], 500);
}
$stmt_check->bind_param("s", $username);
$stmt_check->execute();
$stmt_check->store_result();

if ($stmt_check->num_rows > 0) {
    send_json_response(['error' => 'Username already exists.'], 409); // 409 Conflict
}
$stmt_check->close();

// Hash the password for secure storage.
$hashed_password = password_hash($password, PASSWORD_DEFAULT);

// Insert the new user into the database.
$stmt_insert = $conn->prepare("INSERT INTO users (username, password) VALUES (?, ?)");
if (!$stmt_insert) {
    error_log("DB prepare statement failed (insert): " . $conn->error);
    send_json_response(['error' => 'Database operation failed.'], 500);
}

$stmt_insert->bind_param("ss", $username, $hashed_password);

if ($stmt_insert->execute()) {
    send_json_response(['success' => true, 'message' => 'User registered successfully.'], 201); // 201 Created
} else {
    error_log("DB execute failed (insert): " . $stmt_insert->error);
    send_json_response(['error' => 'Failed to register user.'], 500);
}

$stmt_insert->close();
$conn->close();
?>