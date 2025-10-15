<?php

// --- Unified Configuration and Helpers ---
// The config file is in the same directory.
require_once __DIR__ . '/config.php';

// Define a specific log file path within the backend directory
$debugLogFile = __DIR__ . '/email_handler_debug.log';

// Helper function to write to the debug log file
function write_debug_log($message, $logFile) {
    file_put_contents($logFile, date('[Y-m-d H:i:s]') . ' ' . $message . PHP_EOL, FILE_APPEND);
}

// --- Security Check ---
// This secret ensures that only our Cloudflare Worker can call this endpoint.
$secretToken = getenv('EMAIL_HANDLER_SECRET');
// Use $_REQUEST to accept the secret from either GET or POST requests.
$receivedToken = $_REQUEST['worker_secret'] ?? '';

// Basic logging to help debug authentication issues.
// Changed to write directly to a file for environments without error_log access.
write_debug_log("Email Handler Debug: secretToken (from env) = [" . (isset($secretToken) ? (empty($secretToken) ? 'EMPTY_STRING' : $secretToken) : 'NOT_SET') . "]", $debugLogFile);
write_debug_log("Email Handler Debug: receivedToken (from REQUEST) = [" . (isset($receivedToken) ? (empty($receivedToken) ? 'EMPTY_STRING' : $receivedToken) : 'NOT_SET') . "]", $debugLogFile);

if (empty($secretToken) || $receivedToken !== $secretToken) {
    write_debug_log("Email Handler: Forbidden - Invalid or missing secret token. Received: [" . ($receivedToken ? $receivedToken : "EMPTY_OR_NOT_SET") . "], Expected: [" . ($secretToken ? $secretToken : "EMPTY_OR_NOT_SET") . "]", $debugLogFile);
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Forbidden: Invalid or missing secret token.']);
    exit;
}

// --- Action Routing ---
// Determine the action based on the 'action' query parameter.
$action = $_GET['action'] ?? 'process_email'; // Default to processing email

if ($action === 'is_user_registered') {
    // --- User Verification Logic ---
    $email = $_GET['email'] ?? null;
    if (empty($email)) {
        write_debug_log("Email Handler: Missing email parameter for is_user_registered.", $debugLogFile);
        http_response_code(400);
        echo json_encode(['success' => false, 'is_registered' => false, 'message' => 'Email parameter is missing.']);
        exit;
    }

    try {
        $pdo = get_db_connection();
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $userExists = $stmt->fetchColumn();

        write_debug_log("Email Handler: User '{$email}' registered status: " . ($userExists ? 'true' : 'false'), $debugLogFile);
        
        if ($userExists) {
            echo json_encode(['success' => true, 'is_registered' => true]);
        } else {
            echo json_encode(['success' => true, 'is_registered' => false, 'message' => 'User not found.']);
        }
    } catch (PDOException $e) {
        write_debug_log("Database error in is_user_registered: " . $e->getMessage(), $debugLogFile);
        http_response_code(500);
        echo json_encode(['success' => false, 'is_registered' => false, 'message' => 'Internal server error during user check.']);
    }
    exit;
}


if ($action === 'process_email') {
     // --- Email Processing Logic ---
    $from = $_POST['from'] ?? 'Unknown Sender';
    $to = $_POST['to'] ?? 'Unknown Recipient';
    $subject = $_POST['subject'] ?? 'No Subject';
    $body = $_POST['body'] ?? ''; // The HTML content of the email
    
    // Validate required fields
    if (empty($from) || empty($to) || empty($body)) {
        write_debug_log("Email Handler: Missing required fields (from, to, or body) for process_email.", $debugLogFile);
        http_response_code(400); // Bad Gateway
        echo json_encode(['status' => 'error', 'message' => 'Missing required fields: from, to, or body.']);
        exit;
    }
    
    // Find the user by their email address
    $pdo = get_db_connection();
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$from]);
    $user = $stmt->fetch();
    
    if (!$user) {
        write_debug_log("Email Handler: Received email from '$from' but user not found in DB. Ignoring.", $debugLogFile);
        echo json_encode(['status' => 'success', 'message' => 'User not found, but acknowledged.']);
        exit;
    }
    
    $userId = $user['id'];
    
    // Insert the email into the database
    try {
        $stmt = $pdo->prepare("INSERT INTO emails (user_id, sender, recipient, subject, html_content) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$userId, $from, $to, $subject, $body]);
    
        write_debug_log("Email Handler: Email from '$from' processed successfully.", $debugLogFile);
        http_response_code(200);
        echo json_encode(['status' => 'success', 'message' => 'Email processed successfully.']);
    
    } catch (PDOException $e) {
        write_debug_log("Email Handler DB Error: " . $e->getMessage(), $debugLogFile);
        http_response_code(500); // Internal Server Error
        echo json_encode(['status' => 'error', 'message' => 'Failed to save email to the database.']);
    }

}

// Fallback for unknown actions
write_debug_log("Email Handler: Unknown action: '" . ($action ?? 'null') . "'.", $debugLogFile);
http_response_code(400);
echo json_encode(['status' => 'error', 'message' => 'Unknown action.']);
