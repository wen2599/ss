<?php
require_once __DIR__ . '/bootstrap.php';

write_log("------ login_user.php Entry Point ------");

// --- Input and Validation ---
$data = json_decode(file_get_contents('php://input'), true);

if (!$data) {
    json_response('error', 'Invalid JSON received.', 400);
}

$email = $data['email'] ?? null;
$password = $data['password'] ?? null;

if (empty($email) || empty($password)) {
    json_response('error', 'Email and password are required.', 400);
}

// --- Database and Authentication ---
$pdo = get_db_connection();
$stmt = $pdo->prepare("SELECT id, email, password_hash FROM users WHERE email = ?");
$stmt->execute([$email]);
$user = $stmt->fetch();

if ($user && password_verify($password, $user['password_hash'])) {
    // --- Session Creation ---
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['email'] = $user['email'];

    write_log("User logged in successfully: " . $email . ". Session ID: " . session_id());
    json_response('success', [
        'message' => 'Login successful!',
        'user' => [
            'id' => $user['id'],
                'email' => $user['email']
        ]
    ]);

} else {
    write_log("Login failed for email: " . $email . ". Invalid credentials.");
    json_response('error', 'Invalid email or password.', 401);
}

write_log("------ login_user.php Exit Point ------");
