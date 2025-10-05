<?php
// backend/endpoints/register.php

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    send_json_response(['error' => 'Method not allowed.'], 405);
}

$input = json_decode(file_get_contents('php://input'), true);

if (!$input || !isset($input['username']) || !isset($input['password'])) {
    send_json_response(['error' => 'Username and password are required.'], 400);
}

// The 'username' from the form is treated as the user's email address.
$email = trim($input['username']);
$password = $input['password'];

if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    // Frontend expects 'username', so we message in that context, but validate as email.
    send_json_response(['error' => 'A valid email address must be used as the username.'], 400);
}

if (empty($password)) {
    send_json_response(['error' => 'Password cannot be empty.'], 400);
}

$conn = get_db_connection();
if (!$conn) {
    send_json_response(['error' => 'Database connection failed.'], 500);
}

// --- Step 1: Check if the email is in the allowed list ---
$stmt_allowed = $conn->prepare("SELECT id FROM allowed_emails WHERE email = ?");
if (!$stmt_allowed) {
    error_log("DB prepare statement failed (check allowed): " . $conn->error);
    send_json_response(['error' => 'Database query failed.'], 500);
}
$stmt_allowed->bind_param("s", $email);
$stmt_allowed->execute();
$stmt_allowed->store_result();

if ($stmt_allowed->num_rows === 0) {
    $stmt_allowed->close();
    $conn->close();
    // Email is not on the allowed list.
    send_json_response(['error' => '此邮箱未被授权注册，请联系管理员。'], 403); // 403 Forbidden
}
$stmt_allowed->close();

// --- Step 2: Check if the user is already registered in the 'users' table ---
$stmt_check = $conn->prepare("SELECT username FROM users WHERE username = ?");
if (!$stmt_check) {
    error_log("DB prepare statement failed (check users): " . $conn->error);
    send_json_response(['error' => 'Database query failed.'], 500);
}
$stmt_check->bind_param("s", $email); // We use email as the username
$stmt_check->execute();
$stmt_check->store_result();

if ($stmt_check->num_rows > 0) {
    $stmt_check->close();
    $conn->close();
    send_json_response(['error' => 'Username already exists.'], 409); // 409 Conflict
}
$stmt_check->close();

// --- Step 3: Register the user within a transaction ---
$conn->begin_transaction();

try {
    // Insert the new user. Assume 'username' and 'email' columns exist.
    $stmt_insert = $conn->prepare("INSERT INTO users (username, password, email) VALUES (?, ?, ?)");
    if (!$stmt_insert) throw new Exception("DB prepare statement failed (insert user): " . $conn->error);

    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
    $stmt_insert->bind_param("sss", $email, $hashed_password, $email);
    if (!$stmt_insert->execute()) throw new Exception("DB execute failed (insert user): " . $stmt_insert->error);
    $stmt_insert->close();

    // Delete the email from the allowed list to prevent reuse.
    $stmt_delete = $conn->prepare("DELETE FROM allowed_emails WHERE email = ?");
    if (!$stmt_delete) throw new Exception("DB prepare statement failed (delete allowed): " . $conn->error);
    $stmt_delete->bind_param("s", $email);
    if (!$stmt_delete->execute()) throw new Exception("DB execute failed (delete allowed): " . $stmt_delete->error);
    $stmt_delete->close();

    // If all went well, commit the transaction.
    $conn->commit();

    send_json_response(['success' => true, 'message' => 'User registered successfully.'], 201);

} catch (Exception $e) {
    // Something went wrong, roll back the transaction.
    $conn->rollback();
    error_log($e->getMessage());
    send_json_response(['error' => 'Failed to register user due to a server error.'], 500);
}

$conn->close();
?>