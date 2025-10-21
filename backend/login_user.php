<?php
// backend/login_user.php
// Handles user login and session creation.

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/db_operations.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);

    if (empty($data['email']) || empty($data['password'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Missing email or password.']);
        exit();
    }

    $email = trim($data['email']);
    $password = $data['password'];

    try {
        // Fetch user by username or email
        $user = fetchOne($pdo, "SELECT id, username, email, password FROM users WHERE username = :email OR email = :email", [
            ':email' => $email
        ]);

        if ($user && password_verify($password, $user['password'])) {
            // Password is correct, create a session.
            // Generate a secure session token.
            $sessionToken = bin2hex(random_bytes(32));
            $expiresAt = date('Y-m-d H:i:s', strtotime('+1 day')); // Session expires in 1 day.

            // Store session in the database.
            insert($pdo, 'sessions', [
                'user_id' => $user['id'],
                'session_token' => $sessionToken,
                'expires_at' => $expiresAt
            ]);

            // Set a secure cookie for the session token.
            setcookie('session_token', $sessionToken, [
                'expires' => strtotime('+1 day'),
                'path' => '/',
                'httponly' => true, // HttpOnly prevents JavaScript access to the cookie.
                'secure' => true,   // Secure ensures the cookie is only sent over HTTPS.
                'samesite' => 'Lax' // Or 'Strict', depending on your needs.
            ]);

            http_response_code(200);
            echo json_encode(['message' => 'Login successful.', 'user' => ['id' => $user['id'], 'username' => $user['username'], 'email' => $user['email'] ]]);

        } else {
            http_response_code(401);
            echo json_encode(['error' => 'Invalid username or password.']);
        }

    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
    }
} else {
    http_response_code(405);
    echo json_encode(['error' => 'Invalid request method.']);
}
