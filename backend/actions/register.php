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

if (!$username || !$password) {
    json_response(['error' => 'Username and password are required.'], 400);
}

if (strlen($password) < 8) {
    json_response(['error' => 'Password must be at least 8 characters long.'], 400);
}

// --- Check for existing user ---
try {
    $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
    $stmt->execute([$username]);
    if ($stmt->fetch()) {
        json_response(['error' => 'Username already taken.'], 409); // 409 Conflict
    }
} catch (PDOException $e) {
    // In a real app, log this error
    json_response(['error' => 'Database error during user check.'], 500);
}

// --- Create new user ---
$password_hash = password_hash($password, PASSWORD_DEFAULT);

try {
    $stmt = $pdo->prepare("INSERT INTO users (username, password) VALUES (?, ?)");
    $stmt->execute([$username, $password_hash]);

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