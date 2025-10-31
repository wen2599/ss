<?php
// backend/receive_email.php
// Version 2.2 (DIAGNOSTIC MODE): Temporarily display errors to capture the fatal one.

// --- Temporary Diagnostic Error Handling ---
// Force PHP to display errors directly in the output. This will break the JSON response
// but will allow us to capture the exact fatal error message in the Cloudflare logs.
ini_set('display_errors', 1);
ini_set('log_errors', 1); // Keep logging enabled as a backup
error_reporting(E_ALL);


// Load environment variables and database connection
require_once __DIR__ . '/env_loader.php';
require_once __DIR__ . '/db_connection.php';

// --- Global Helper for JSON responses ---
function send_json($data, $statusCode = 200) {
    http_response_code($statusCode);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

// --- Primary Security Check: Validate the Worker Secret ---
$worker_secret = getenv('EMAIL_HANDLER_SECRET');
if (!$worker_secret) {
    // Note: In diagnostic mode, this error might be displayed directly.
    die('FATAL ERROR: Server configuration missing - EMAIL_HANDLER_SECRET is not set.');
}

$received_secret = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $received_secret = $_POST['worker_secret'] ?? '';
} else {
    $received_secret = $_GET['worker_secret'] ?? '';
}

if ($received_secret !== $worker_secret) {
    die('FATAL ERROR: Forbidden - Invalid credentials provided.');
}

// --- Action Router ---
$action = $_GET['action'] ?? '';

switch ($action) {
    case 'is_user_registered':
        handle_is_user_registered();
        break;
    case 'process_email':
        handle_process_email();
        break;
    default:
        // This part might not be reached if a fatal error occurs earlier.
        send_json(['success' => false, 'message' => 'Bad Request: No or invalid action specified.'], 400);
}


// --- Action Handler: is_user_registered ---
function handle_is_user_registered() {
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        send_json(['success' => false, 'message' => 'Method Not Allowed for this action.'], 405);
    }
    
    $email = $_GET['email'] ?? null;
    if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        send_json(['success' => false, 'message' => 'Bad Request: Missing or invalid email.'], 400);
    }

    $conn = get_db_connection(); // Let it throw exception on failure
    $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $stmt->store_result();
    
    $is_registered = $stmt->num_rows > 0;
    
    $stmt->close();
    $conn->close();

    send_json(['success' => true, 'is_registered' => $is_registered]);
}


// --- Action Handler: process_email ---
function handle_process_email() {
    // ... (rest of the function remains the same, but might not be reached)
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        send_json(['success' => false, 'message' => 'Method Not Allowed for this action.'], 405);
    }

    $from = $_POST['from'] ?? null;
    $subject = $_POST['subject'] ?? '';
    $body = $_POST['body'] ?? '';

    if (!$from) {
        send_json(['success' => false, 'message' => 'Bad Request: Missing from address.'], 400);
    }

    $conn = get_db_connection();

    $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->bind_param("s", $from);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $stmt->close();

    if (!$user) {
        send_json(['success' => false, 'message' => 'User not found.'], 404);
    }
    $user_id = $user['id'];

    $stmt_insert = $conn->prepare("INSERT INTO user_emails (user_id, from_address, subject, body) VALUES (?, ?, ?, ?)");
    $stmt_insert->bind_param("isss", $user_id, $from, $subject, $body);
    
    if ($stmt_insert->execute()) {
        send_json(['success' => true, 'message' => 'Email saved successfully for user.'], 201);
    } else {
        // In diagnostic mode, this will now be visible if it happens
        throw new Exception("Failed to execute insert statement: " . $stmt_insert->error);
    }
    
    $stmt_insert->close();
    $conn->close();
}

?>