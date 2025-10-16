<?php
error_log("Register_user.php script started.");

require_once __DIR__ . '/api_header.php';
require_once __DIR__ . '/db_operations.php'; // Add this line to include database operations

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
error_log("Attempting to get database connection in register_user.php");
$pdo = get_db_connection();
if (!$pdo) {
    error_log("Failed to get database connection in register_user.php");
    http_response_code(503); // Service Unavailable
    echo json_encode(['error' => 'Database connection is currently unavailable.']);
    exit;
}
error_log("Successfully connected to database in register_user.php");

// Hash the password for secure storage
$password_hash = password_hash($password, PASSWORD_DEFAULT);

try {
    // 1. Check if email already exists (since username is the email)
    error_log("Checking if email " . $email . " already exists.");
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
        http_response_code(409); // Conflict
        echo json_encode(['error' => 'A user with this email already exists.']);
        exit;
    }
    error_log("Email " . $email . " does not exist, proceeding to registration.");

    // 2. Insert the new user into the database
    error_log("Attempting to insert new user: " . $email);
    $stmt = $pdo->prepare(
        "INSERT INTO users (username, email, password_hash) VALUES (?, ?, ?)"
    );

    $isSuccess = $stmt->execute([$username, $email, $password_hash]);

    if ($isSuccess) {
        // Automatically log the user in by creating a session.
        $user_id = $pdo->lastInsertId();
        $_SESSION['user_id'] = $user_id;
        $_SESSION['email'] = $email;

        error_log("User registered successfully: " . $email);
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
        error_log("Failed to register user " . $email . " due to server issue.");
        http_response_code(500); // Internal Server Error
        echo json_encode(['error' => 'Failed to register the user due to a server issue.']);
    }

} catch (PDOException $e) {
    error_log("User registration error: " . $e->getMessage());
    http_response_code(500);
    // Temporarily expose the detailed error for debugging
    echo json_encode([
        'error' => 'An internal database error occurred.',
        'db_error' => $e->getMessage()
    ]);
}

?>