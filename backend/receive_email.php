<?php
// backend/receive_email.php
// Version 2.1: Added production error handling to suppress warnings in JSON response.

// --- Production-Safe Error Handling ---
// Prevent any PHP warnings or errors from being displayed in the output,
// as this will break the JSON response expected by the Cloudflare Worker.
ini_set('display_errors', 0);
// Log errors to the server's error log instead of displaying them.
ini_set('log_errors', 1);
// Report all types of errors to be logged.
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
    error_log('Security Error: EMAIL_HANDLER_SECRET is not set in the environment.');
    send_json(['success' => false, 'message' => 'Server configuration error.'], 500);
}

$received_secret = '';
// The secret is sent differently depending on the request type from the worker
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $received_secret = $_POST['worker_secret'] ?? '';
} else {
    $received_secret = $_GET['worker_secret'] ?? '';
}

if ($received_secret !== $worker_secret) {
    error_log("Forbidden: Invalid worker secret. Got: '{$received_secret}'");
    send_json(['success' => false, 'message' => 'Forbidden: Invalid credentials.'], 403);
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

    $conn = null;
    try {
        $conn = get_db_connection();
        $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $stmt->store_result();
        
        $is_registered = $stmt->num_rows > 0;
        
        $stmt->close();
        $conn->close();

        send_json(['success' => true, 'is_registered' => $is_registered]);

    } catch (Exception $e) {
        error_log("DB Error in is_user_registered: " . $e->getMessage());
        send_json(['success' => false, 'message' => 'Internal Server Error.'], 500);
    }
}


// --- Action Handler: process_email ---
function handle_process_email() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        send_json(['success' => false, 'message' => 'Method Not Allowed for this action.'], 405);
    }

    // Extract data from FormData
    $from = $_POST['from'] ?? null;
    $subject = $_POST['subject'] ?? '';
    $body = $_POST['body'] ?? '';

    if (!$from) {
        send_json(['success' => false, 'message' => 'Bad Request: Missing from address.'], 400);
    }

    $conn = null;
    try {
        $conn = get_db_connection();

        // 1. Find the user_id based on the 'from' email address
        $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->bind_param("s", $from);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        $stmt->close();

        if (!$user) {
            // This should technically not happen if the worker logic is correct, but as a safeguard:
            send_json(['success' => false, 'message' => 'User not found.'], 404);
        }
        $user_id = $user['id'];

        // 2. Insert the email into the `emails` table
        $stmt_insert = $conn->prepare("INSERT INTO emails (user_id, from_address, subject, body_text) VALUES (?, ?, ?, ?)");
        $stmt_insert->bind_param("isss", $user_id, $from, $subject, $body);
        
        if ($stmt_insert->execute()) {
            send_json(['success' => true, 'message' => 'Email saved successfully for user.'], 201);
        } else {
            throw new Exception("Failed to execute insert statement: " . $stmt_insert->error);
        }
        
        $stmt_insert->close();
        $conn->close();

    } catch (Exception $e) {
        error_log("DB Error in process_email: " . $e->getMessage());
        send_json(['success' => false, 'message' => 'Internal Server Error.'], 500);
    }
}

?>