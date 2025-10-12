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

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        Response::json(['error' => 'Invalid email format'], 400);
        exit;
    }

    if (strlen($password) < 8) {
        Response::json(['error' => 'Password must be at least 8 characters long'], 400);
        exit;
    }

    $db = Database::getInstance();
    $conn = $db->getConnection();

    // Check if user already exists
    $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $stmt->store_result();
    if ($stmt->num_rows > 0) {
        Response::json(['error' => 'An account with this email already exists.'], 409);
        $stmt->close();
        $conn->close();
        exit;
    }
    $stmt->close();

    // Insert new user
    $password_hash = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $conn->prepare("INSERT INTO users (email, password_hash) VALUES (?, ?)");
    $stmt->bind_param("ss", $email, $password_hash);

    if ($stmt->execute()) {
        $user_id = $stmt->insert_id;

        // Start session and log the user in automatically
        $_SESSION['user_id'] = $user_id;
        $_SESSION['email'] = $email;

        Response::json(['message' => 'Registration successful. You are now logged in.'], 201);
    } else {
        error_log("Registration failed: " . $stmt->error);
        Response::json(['error' => 'An error occurred during registration. Please try again.'], 500);
    }

    $stmt->close();
    $conn->close();
}
