<?php
// 文件名: register.php
// 路径: backend/api/auth/register.php
ini_set('display_errors', 1); error_reporting(E_ALL);

require_once __DIR__ . '/../../core/db.php';
require_once __DIR__ . '/../../core/helpers.php';

$data = json_decode(file_get_contents('php://input'), true);
$email = $data['email'] ?? null;
$password = $data['password'] ?? null;

if (empty($email) || empty($password)) {
    json_response(['message' => 'Email and password are required.'], 400);
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    json_response(['message' => 'Invalid email format.'], 400);
}

if (strlen($password) < 6) {
    json_response(['message' => 'Password must be at least 6 characters long.'], 400);
}

try {
    $db = get_db_connection();
    
    $stmt = $db->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
        json_response(['message' => 'Email already exists.'], 409);
    }

    $password_hash = password_hash($password, PASSWORD_BCRYPT);

    $stmt = $db->prepare("INSERT INTO users (email, password_hash) VALUES (?, ?)");
    $stmt->execute([$email, $password_hash]);

    json_response(['message' => 'User registered successfully.'], 201);

} catch (PDOException $e) {
    error_log("Register API Error: " . $e->getMessage());
    json_response(['message' => 'Database error during registration.'], 500);
}