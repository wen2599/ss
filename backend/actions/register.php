<?php
// Handles new user registration
require_once __DIR__ . '/../init.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['error' => 'Only POST method is allowed.'], 405);
}

$data = json_decode(file_get_contents('php://input'), true);

// --- Input Validation ---
$username = $data['username'] ?? null;
$password = $data['password'] ?? null;
$email = $data['email'] ?? null;

if (!$username || !$password || !$email) {
    json_response(['error' => '用户名、密码和邮箱均为必填项。'], 400);
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    json_response(['error' => '无效的邮箱格式。'], 400);
}

if (strlen($password) < 8) {
    json_response(['error' => '密码长度至少为8位。'], 400);
}

// --- Check for existing user or email ---
try {
    $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
    $stmt->execute([$username, $email]);
    if ($stmt->fetch()) {
        // More specific error message could be implemented on the frontend if needed
        json_response(['error' => '用户名或邮箱已被占用。'], 409); // 409 Conflict
    }
} catch (PDOException $e) {
    // In a real app, log this error
    json_response(['error' => '数据库错误：无法检查用户。'], 500);
}

// --- Create new user ---
$password_hash = password_hash($password, PASSWORD_DEFAULT);

try {
    $stmt = $pdo->prepare("INSERT INTO users (username, email, password) VALUES (?, ?, ?)");
    $stmt->execute([$username, $email, $password_hash]);

    if ($stmt->rowCount() > 0) {
        json_response(['message' => 'User registered successfully.'], 201);
    } else {
        json_response(['error' => 'Failed to register user.'], 500);
    }
} catch (PDOException $e) {
    // In a real app, log this error
    json_response(['error' => 'Database error during user creation.'], 500);
}
?>