<?php

// --- Unified Configuration and Helpers ---
require_once __DIR__ . '/config.php';

// --- Security Check ---
// This secret ensures that only our Cloudflare Worker can call this endpoint.
$secretToken = getenv('EMAIL_HANDLER_SECRET');
// Use $_REQUEST to accept the secret from either GET or POST requests.
$receivedToken = $_REQUEST['worker_secret'] ?? '';

error_log("Email Handler Debug: secretToken (from env) = [" . ($secretToken ? $secretToken : "EMPTY") . "]");
error_log("Email Handler Debug: receivedToken (from REQUEST) = [" . ($receivedToken ? $receivedToken : "EMPTY") . "]");

if (empty($secretToken) || $receivedToken !== $secretToken) {
    error_log("Email Handler: Forbidden - Invalid or missing secret token. Received: " . ($receivedToken ? $receivedToken : "[EMPTY]") . ", Expected: " . ($secretToken ? $secretToken : "[EMPTY]"));
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
        http_response_code(400);
        echo json_encode(['success' => false, 'is_registered' => false, 'message' => 'Email parameter is missing.']);
        exit;
    }

    try {
        $pdo = get_db_connection();
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $userExists = $stmt->fetchColumn();

        if ($userExists) {
            echo json_encode(['success' => true, 'is_registered' => true]);
        } else {
            echo json_encode(['success' => true, 'is_registered' => false, 'message' => 'User not found.']);
        }
    } catch (PDOException $e) {
        error_log("Database error in is_user_registered: " . $e->getMessage());
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
        http_response_code(400); // Bad Request
        echo json_encode(['status' => 'error', 'message' => 'Missing required fields: from, to, or body.']);
        exit;
    }
    
    // Find the user by their email address
    $pdo = get_db_connection();
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$from]);
    $user = $stmt->fetch();
    
    if (!$user) {
        // Important: We stop here but don't return an error to the Worker,
        // as the Worker has already checked for user registration.
        // Logging is sufficient.
        error_log("Email Handler: Received email from '$from' but user no longer exists in DB. Ignoring.");
        // Return a success to prevent the worker from retrying.
        echo json_encode(['status' => 'success', 'message' => 'User not found, but acknowledged.']);
        exit;
    }
    
    $userId = $user['id'];
    
    // Insert the email into the database
    try {
        $stmt = $pdo->prepare("INSERT INTO emails (user_id, sender, recipient, subject, html_content) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$userId, $from, $to, $subject, $body]);
    
        http_response_code(200);
        echo json_encode(['status' => 'success', 'message' => 'Email processed successfully.']);
    
    } catch (PDOException $e) {
        error_log("Email Handler DB Error: " . $e->getMessage());
        http_response_code(500); // Internal Server Error
        echo json_encode(['status' => 'error', 'message' => 'Failed to save email to the database.']);
    }

}

// Fallback for unknown actions
http_response_code(400);
echo json_encode(['status' => 'error', 'message' => 'Unknown action.']);

?>
