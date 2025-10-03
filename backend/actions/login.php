<?php
// Handles user login
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

// --- Find user and verify password ---
try {
    $stmt = $pdo->prepare("SELECT id, username, password, is_admin FROM users WHERE username = ?");
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password'])) {
        // Password is correct, start a session
        session_regenerate_id(); // Prevent session fixation
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['is_admin'] = $user['is_admin'];

        json_response([
            'message' => 'Login successful.',
            'user' => [
                'id' => $user['id'],
                'username' => $user['username'],
                'is_admin' => $user['is_admin']
            ]
        ], 200);
    } else {
        // Bad credentials
        json_response(['error' => 'Invalid username or password.'], 401);
    }
} catch (PDOException $e) {
    // In a real app, log this error
    json_response(['error' => 'Database error during login.'], 500);
}
?>