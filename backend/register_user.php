<?php
/**
 * register_user.php (Refactored for Correct Inclusion)
 *
 * This script handles user registration. It now only includes the main API header,
 * which is responsible for loading the entire application configuration and all
 * necessary dependencies like db_operations.php. This prevents duplicate inclusions.
 */

require_once __DIR__ . '/api_header.php';

// The db_operations.php file is now loaded via config.php, which is included in api_header.php.
// No further require_once statements are needed.

// --- Input Reception and Validation ---
$data = json_decode(file_get_contents('php://input'), true);

if (!$data) {
    http_response_code(400); // Bad Request
    echo json_encode(['error' => 'Invalid JSON received.']);
    exit;
}

$email = $data['email'] ?? null;
$password = $data['password'] ?? null;
$username = $email; // The username is the email.

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

if (is_array($pdo) && isset($pdo['db_error'])) {
    error_log("Failed to get database connection in register_user.php: " . $pdo['db_error']);
    http_response_code(503); // Service Unavailable
    echo json_encode([
        'error' => 'Database connection is currently unavailable.',
        'details' => $pdo['db_error']
    ]);
    exit;
}

if (!$pdo) {
    error_log("Failed to get database connection in register_user.php (returned null).");
    http_response_code(503); // Service Unavailable
    echo json_encode(['error' => 'Database connection is currently unavailable.']);
    exit;
}

// Hash the password for secure storage
$password_hash = password_hash($password, PASSWORD_DEFAULT);

try {
    // Check if email already exists
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
        http_response_code(409); // Conflict
        echo json_encode(['error' => 'A user with this email already exists.']);
        exit;
    }

    // Insert the new user into the database
    $stmt = $pdo->prepare(
        "INSERT INTO users (username, email, password_hash) VALUES (?, ?, ?)"
    );

    if ($stmt->execute([$username, $email, $password_hash])) {
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
        error_log("Failed to register user " . $email . " due to a server issue.");
        http_response_code(500); // Internal Server Error
        echo json_encode(['error' => 'Failed to register the user due to a server issue.']);
    }

} catch (PDOException $e) {
    error_log("User registration error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'error' => 'An internal database error occurred.',
        'db_error' => $e->getMessage()
    ]);
}
?>
