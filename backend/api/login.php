<?php
require_once __DIR__ . '/../src/config.php'; // Includes session_start()

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);

    $email = $data['email'] ?? null;
    $password = $data['password'] ?? null;

    if (empty($email) || empty($password)) {
        Response::json(['error' => 'Email and password are required'], 400);
        exit;
    }

    $db = Database::getInstance();
    $conn = $db->getConnection();

    $stmt = $conn->prepare('SELECT id, password_hash FROM users WHERE email = ?');
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();

    if ($user && password_verify($password, $user['password_hash'])) {
        // User is authenticated, store user info in session
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['email'] = $email;

        Response::json(['message' => 'Login successful']);
    } else {
        Response::json(['error' => 'Invalid email or password'], 401);
    }

    $stmt->close();
    $conn->close();
}
