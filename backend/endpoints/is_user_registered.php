<?php
// backend/endpoints/is_user_registered.php

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    send_json_response(['error' => 'Method not allowed.'], 405);
}

// 1. Validate Worker Secret
$worker_secret = $_GET['worker_secret'] ?? '';
if ($worker_secret !== WORKER_SECRET) {
    send_json_response(['error' => 'Unauthorized.'], 401);
}

// 2. Get and Validate Email
$user_email = $_GET['email'] ?? '';
if (empty($user_email) || !filter_var($user_email, FILTER_VALIDATE_EMAIL)) {
    send_json_response(['error' => 'Invalid email provided.'], 400);
}

// 3. Check if User Exists in the Database
$conn = get_db_connection();
if (!$conn) {
    send_json_response(['error' => 'Database connection failed.'], 500);
}

// Use a prepared statement to prevent SQL injection.
// Assumes a table named 'users' with an 'email' column.
$stmt = $conn->prepare("SELECT email FROM users WHERE email = ?");
if (!$stmt) {
    // Log the actual error for debugging, but send a generic message to the client.
    error_log("DB prepare statement failed: " . $conn->error);
    send_json_response(['error' => 'Database query failed.'], 500);
}

$stmt->bind_param("s", $user_email);
$stmt->execute();
$stmt->store_result();

$is_registered = ($stmt->num_rows > 0);

$stmt->close();
$conn->close();

// 4. Send Response
send_json_response([
    'success' => true,
    'is_registered' => $is_registered
]);
?>