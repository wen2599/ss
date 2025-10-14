<?php

error_log("--- register_user.php: Script started ---");

require_once 'api_header.php';

error_log("--- register_user.php: api_header.php loaded ---");

// --- Input Reception and Validation ---
$data = json_decode(file_get_contents('php://input'), true);
error_log("--- register_user.php: Input data decoded ---");

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
error_log("--- register_user.php: Attempting to get database connection ---");
$pdo = get_db_connection();
if (!$pdo) {
    error_log("--- register_user.php: FAILED to get database connection ---");
    http_response_code(503); // Service Unavailable
    echo json_encode(['error' => 'Database connection is currently unavailable.']);
    exit;
}

// Hash the password for secure storage
$password_hash = password_hash($password, PASSWORD_DEFAULT);

try {
    error_log("--- register_user.php: Inside try block, about to check for existing email ---");
    // 1. Check if email already exists (since username is the email)
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
        http_response_code(409); // Conflict
        echo json_encode(['error' => 'A user with this email already exists.']);
        exit;
    }

    // 2. Insert the new user into the database
    error_log("--- register_user.php: About to prepare INSERT statement ---");
    $stmt = $pdo->prepare(
        "INSERT INTO users (username, email, password_hash) VALUES (?, ?, ?)"
    );

    error_log("--- register_user.php: About to execute INSERT statement ---");
    $isSuccess = $stmt->execute([$username, $email, $password_hash]);
    error_log("--- register_user.php: INSERT statement executed, success=" . ($isSuccess ? 'true' : 'false') . " ---");

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
