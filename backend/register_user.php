<?php
error_log("Register_user.php script started.");

require_once __DIR__ . '/api_header.php';
require_once __DIR__ . '/db_operations.php'; // Add this line to include database operations

// Add custom debug logging for register_user.php as well
function write_custom_debug_log_register($message) {
    $logFile = __DIR__ . '/env_debug.log';
    file_put_contents($logFile, date('[Y-m-d H:i:s]') . ' [Register_User] ' . $message . PHP_EOL, FILE_APPEND | LOCK_EX);
}

write_custom_debug_log_register("------ Register_user.php Entry Point ------");

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
write_custom_debug_log_register("Attempting to get database connection in register_user.php");
// Log environment variables BEFORE calling get_db_connection
write_custom_debug_log_register("Register_user.php: DB_HOST before DB connection: " . (getenv('DB_HOST') ?: 'N/A'));
write_custom_debug_log_register("Register_user.php: DB_PORT before DB connection: " . (getenv('DB_PORT') ?: 'N/A'));
write_custom_debug_log_register("Register_user.php: DB_DATABASE before DB connection: " . (getenv('DB_DATABASE') ?: 'N/A'));
write_custom_debug_log_register("Register_user.php: DB_USER before DB connection: " . (getenv('DB_USER') ?: 'N/A'));

$pdo = get_db_connection();
// Check if get_db_connection returned an error array
if (is_array($pdo) && isset($pdo['db_error'])) {
    error_log("Failed to get database connection in register_user.php: " . $pdo['db_error']);
    http_response_code(503); // Service Unavailable
    echo json_encode([
        'error' => 'Database connection is currently unavailable.',
        'details' => $pdo['db_error'] // Expose detailed DB error for debugging
    ]);
    exit;
}

// Original check for null $pdo (for older behavior or unexpected nulls)
if (!$pdo) {
    error_log("Failed to get database connection in register_user.php (returned null).");
    http_response_code(503); // Service Unavailable
    echo json_encode(['error' => 'Database connection is currently unavailable.']);
    exit;
}

error_log("Successfully connected to database in register_user.php");
write_custom_debug_log_register("Successfully connected to database in register_user.php.");

// Hash the password for secure storage
$password_hash = password_hash($password, PASSWORD_DEFAULT);

try {
    // 1. Check if email already exists (since username is the email)
    error_log("Checking if email " . $email . " already exists.");
    write_custom_debug_log_register("Checking if email " . $email . " already exists.");
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
        http_response_code(409); // Conflict
        echo json_encode(['error' => 'A user with this email already exists.']);
        exit;
    }
    error_log("Email " . $email . " does not exist, proceeding to registration.");
    write_custom_debug_log_register("Email " . $email . " does not exist, proceeding to registration.");

    // 2. Insert the new user into the database
    error_log("Attempting to insert new user: " . $email);
    write_custom_debug_log_register("Attempting to insert new user: " . $email);
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
        write_custom_debug_log_register("User registered successfully: " . $email);
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
        write_custom_debug_log_register("Failed to register user " . $email . " due to server issue.");
        http_response_code(500); // Internal Server Error
        echo json_encode(['error' => 'Failed to register the user due to a server issue.']);
    }

} catch (PDOException $e) {
    error_log("User registration error: " . $e->getMessage());
    write_custom_debug_log_register("User registration error: " . $e->getMessage());
    http_response_code(500);
    // Temporarily expose the detailed error for debugging
    echo json_encode([
        'error' => 'An internal database error occurred.',
        'db_error' => $e->getMessage()
    ]);
}

write_custom_debug_log_register("------ Register_user.php Exit Point ------");

?>