<?php

// --- Security Check ---
// This secret ensures that only our Cloudflare Worker can call this endpoint.
$secretToken = getenv('EMAIL_HANDLER_SECRET');
// Use $_REQUEST to accept the secret from either GET or POST requests.
$receivedToken = $_REQUEST['worker_secret'] ?? '';

if (empty($secretToken) || $receivedToken !== $secretToken) {
    write_log("Email Handler: Forbidden - Invalid or missing secret token.");
    json_response('error', 'Forbidden: Invalid or missing secret token.', 403);
}

// --- Action Routing ---
// Determine the action based on the 'action' query parameter.
$action = $_GET['action'] ?? 'process_email'; // Default to processing email

if ($action === 'is_user_registered') {
    // --- User Verification Logic ---
    $email = $_GET['email'] ?? null;
    if (empty($email)) {
        write_log("Email Handler: Missing email parameter for is_user_registered.");
        json_response('error', 'Email parameter is missing.', 400);
    }

    $pdo = get_db_connection();
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $userExists = $stmt->fetchColumn();

    write_log("Email Handler: User '{$email}' registered status: " . ($userExists ? 'true' : 'false'));

    if ($userExists) {
        json_response('success', ['is_registered' => true]);
    } else {
        json_response('success', ['is_registered' => false, 'message' => 'User not found.']);
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
        write_log("Email Handler: Missing required fields (from, to, or body) for process_email.");
        json_response('error', 'Missing required fields: from, to, or body.', 400);
    }
    
    $pdo = get_db_connection();
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$from]);
    $user = $stmt->fetch();

    if (!$user) {
        write_log("Email Handler: Received email from '$from' but user not found in DB. Ignoring.");
        json_response('success', 'User not found, but acknowledged.');
    }

    $userId = $user['id'];

    // Insert the email into the database
    $stmt = $pdo->prepare("INSERT INTO emails (user_id, sender, recipient, subject, html_content) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([$userId, $from, $to, $subject, $body]);

    write_log("Email Handler: Email from '$from' processed successfully.");
    json_response('success', 'Email processed successfully.');
    
    exit; // Exit after successful processing
}

// Fallback for unknown actions
write_log("Email Handler: Unknown action: '" . ($action ?? 'null') . "'.");
json_response('error', 'Unknown action.', 400);
