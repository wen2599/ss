<?php

require_once __DIR__ . '/api_header.php';

// --- Input Reception and Validation ---
$data = json_decode(file_get_contents('php://input'), true);

if (!$data) {
    http_response_code(400); // Bad Request
    echo json_encode(['error' => 'Invalid JSON received.']);
    exit;
}

$email = $data['email'] ?? null;
$password = $data['password'] ?? null;

// The username is now the email.
$username = $email;

if (empty($email) || empty($password)) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing required fields: email and password.']);
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid email format.']);
    exit;
}

// --- Database Interaction ---
$pdo = get_db_connection();
if (!$pdo) {
    http_response_code(503); // Service Unavailable
    echo json_encode(['error' => 'Database connection is currently unavailable.']);
    exit;
}

// Hash the password for secure storage
$password_hash = password_hash($password, PASSWORD_DEFAULT);

try {
    // 1. Check if email already exists (since username is the email)
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
        http_response_code(409); // Conflict
        echo json_encode(['error' => 'A user with this email already exists.']);
        exit;
    }

    // 2. Insert the new user into the database
    $stmt = $pdo->prepare(
        "INSERT INTO users (username, email, password_hash, created_at) VALUES (?, ?, ?, NOW())"
    );

    $isSuccess = $stmt->execute([$username, $email, $password_hash]);

    if ($isSuccess) {
        // Automatically log the user in by creating a session.
        $user_id = $pdo->lastInsertId();
        $_SESSION['user_id'] = $user_id;
        $_SESSION['email'] = $email;

        http_response_code(201); // Created
        echo json_encode([
            'message' => 'User registered successfully.',
            'user' => [
                'id' => $user_id,
                'email' => $email,
                'username' => $username
            ]
        ]);
    } else {
        http_response_code(500); // Internal Server Error
        echo json_encode(['error' => 'Failed to register the user due to a server issue.']);
    }

} catch (PDOException $e) {
    error_log("User registration error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'An internal database error occurred.']);
}

?>
