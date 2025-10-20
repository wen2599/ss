<?php
require_once __DIR__ . '/bootstrap.php';

write_log("------ register_user.php Entry Point ------");

// --- Input Reception and Validation ---
$data = json_decode(file_get_contents('php://input'), true);

if (!$data) {
    json_response('error', 'Invalid JSON received.', 400);
}

$email = $data['email'] ?? null;
$password = $data['password'] ?? null;

$username = $email; // The username is now the email.

if (empty($email) || empty($password)) {
    json_response('error', 'Missing required fields: email and password.', 400);
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    json_response('error', 'Invalid email format.', 400);
}

// --- Database Interaction ---
$pdo = get_db_connection();
// 1. Check if email already exists
$stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
$stmt->execute([$email]);
if ($stmt->fetch()) {
    json_response('error', 'A user with this email already exists.', 409);
}

// 2. Insert the new user into the database
$password_hash = password_hash($password, PASSWORD_DEFAULT);
$stmt = $pdo->prepare(
    "INSERT INTO users (email, password_hash) VALUES (?, ?)"
);

$isSuccess = $stmt->execute([$email, $password_hash]);

if ($isSuccess) {
    // Automatically log the user in by creating a session.
    $user_id = $pdo->lastInsertId();
    $_SESSION['user_id'] = $user_id;
    $_SESSION['email'] = $email;

    json_response('success', [
        'message' => 'User registered successfully.',
        'user' => [
            'id' => $user_id,
                'email' => $email
        ]
    ], 201);
} else {
    json_response('error', 'Failed to register the user due to a server issue.', 500);
}

write_log("------ register_user.php Exit Point ------");
