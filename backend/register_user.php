<?php
// backend/register_user.php
// Handles user registration.

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/db_operations.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get the raw POST data.
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);

    // Validate input data.
    if (empty($data['username']) || empty($data['password']) || empty($data['email'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Missing required fields.']);
        exit();
    }

    $username = trim($data['username']);
    $password = $data['password']; // Password will be hashed.
    $email = trim($data['email']);

    // Basic email format validation.
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid email format.']);
        exit();
    }

    // Hash the password for security.
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);

    try {
        // Check if username or email already exists.
        $existingUser = fetchOne($pdo, "SELECT id FROM users WHERE username = :username OR email = :email", [
            ':username' => $username,
            ':email' => $email
        ]);

        if ($existingUser) {
            http_response_code(409); // Conflict
            echo json_encode(['error' => 'Username or email already exists.']);
            exit();
        }

        // Insert new user into the database.
        $userId = insert($pdo, 'users', [
            'username' => $username,
            'password' => $hashed_password,
            'email' => $email
        ]);

        if ($userId) {
            http_response_code(201); // Created
            echo json_encode(['message' => 'User registered successfully.', 'userId' => $userId]);
        } else {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to register user.']);
        }

    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
    }
} else {
    http_response_code(405); // Method Not Allowed
    echo json_encode(['error' => 'Invalid request method.']);
}
