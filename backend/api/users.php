<?php
require_once __DIR__ . '/../core/initialize.php';

$method = $_SERVER['REQUEST_METHOD'];
$endpoint = substr(strtok($_SERVER['REQUEST_URI'], '?'), strlen('/api/'));

if ($method === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    $email = $data['email'] ?? '';
    $password = $data['password'] ?? '';

    if (empty($email) || empty($password)) {
        error_response(400, 'Email and password are required.');
    }

    if ($endpoint === 'users/register') {
        // Registration logic
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        try {
            $stmt = $pdo->prepare("INSERT INTO users (email, password_hash) VALUES (?, ?)");
            $stmt->execute([$email, $hashed_password]);
            json_response(201, ['message' => 'User registered successfully.']);
        } catch (PDOException $e) {
            if ($e->errorInfo[1] == 1062) { // Duplicate entry
                error_response(409, 'Email already exists.');
            } else {
                error_response(500, 'Database error during registration.');
            }
        }
    } elseif ($endpoint === 'users/login') {
        // Login logic
        $stmt = $pdo->prepare("SELECT id, email, password_hash FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && password_verify($password, $user['password_hash'])) {
            $token = create_jwt(['user_id' => $user['id'], 'email' => $user['email']]);
            json_response(200, ['token' => $token, 'email' => $user['email']]);
        } else {
            error_response(401, 'Invalid credentials.');
        }
    }
} else {
    error_response(405, 'Method Not Allowed');
}
