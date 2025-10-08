<?php
// backend/endpoints/register.php

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../lib/helpers.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    send_json_response(['error' => 'Method not allowed.'], 405);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

// We now expect 'email' instead of 'username' from the frontend.
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

if (empty($password)) {
    send_json_response(['error' => 'Password cannot be empty.'], 400);
    exit;
}

// Use the global $db connection from bootstrap.php
global $db;
if (!$db) {
    send_json_response(['error' => 'Database connection failed.'], 500);
    exit;
}

// --- Main Registration Logic with Transaction ---

$db->begin_transaction();

try {
    // Step 1: Check if the email is in the allowed list (lock the row for update).
    $stmt_allowed = $db->prepare("SELECT id FROM allowed_emails WHERE email = ? FOR UPDATE");
    if (!$stmt_allowed) throw new Exception('DB prepare failed (check allowed).');
    $stmt_allowed->bind_param("s", $email);
    $stmt_allowed->execute();
    $stmt_allowed->store_result();

    if ($stmt_allowed->num_rows === 0) {
        // This is a specific, expected error for the frontend.
        send_json_response(['error' => '需要管理员授权的邮箱才能注册'], 403); // 403 Forbidden
        $stmt_allowed->close();
        $db->rollback(); // No need to proceed
        $db->close();
        exit;
    }
    $stmt_allowed->close();

    // Step 2: Check if the user is already registered.
    $stmt_check = $db->prepare("SELECT id FROM users WHERE email = ?");
    if (!$stmt_check) throw new Exception('DB prepare failed (check user).');
    $stmt_check->bind_param("s", $email);
    $stmt_check->execute();
    $stmt_check->store_result();

    if ($stmt_check->num_rows > 0) {
        send_json_response(['error' => 'This email is already registered.'], 409); // 409 Conflict
        $stmt_check->close();
        $db->rollback(); // No need to proceed
        $db->close();
        exit;
    }
    $stmt_check->close();

    // Step 3: Insert the new user.
    $stmt_insert = $db->prepare("INSERT INTO users (email, password) VALUES (?, ?)");
    if (!$stmt_insert) throw new Exception('DB prepare failed (insert user).');
    
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
    $stmt_insert->bind_param("ss", $email, $hashed_password);
    if (!$stmt_insert->execute()) throw new Exception('DB execute failed (insert user).');
    $stmt_insert->close();

    // Step 4: Delete the email from the allowed list to prevent reuse.
    $stmt_delete = $db->prepare("DELETE FROM allowed_emails WHERE email = ?");
    if (!$stmt_delete) throw new Exception('DB prepare failed (delete allowed).');
    $stmt_delete->bind_param("s", $email);
    if (!$stmt_delete->execute()) throw new Exception('DB execute failed (delete allowed).');
    $stmt_delete->close();

    // If all steps succeeded, commit the transaction.
    $db->commit();

    send_json_response(['success' => true, 'message' => 'User registered successfully.'], 201);

} catch (Exception $e) {
    // If any step failed, roll back the entire transaction.
    $db->rollback();
    error_log($e->getMessage());
    send_json_response(['error' => 'An internal server error occurred during registration.'], 500);
}

$db->close();
?>