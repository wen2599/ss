<?php
// backend/handlers/get_email_content.php

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    send_json_response(['status' => 'error', 'message' => 'Invalid request method.'], 405);
}

// User authentication is handled in api.php
if (!isset($current_user_id)) {
     send_json_response(['status' => 'error', 'message' => 'Authentication required.'], 401);
}

$email_id = $_GET['email_id'] ?? null;
if (empty($email_id)) {
    send_json_response(['status' => 'error', 'message' => 'Email ID is required.'], 400);
}

try {
    // Fetch both parsed_content and raw_email, ensuring it belongs to the current user
    $stmt = $pdo->prepare("SELECT parsed_content, raw_email FROM user_emails WHERE id = ? AND user_id = ?");
    $stmt->execute([$email_id, $current_user_id]);
    $email = $stmt->fetch();

    if (!$email) {
        send_json_response(['status' => 'error', 'message' => 'Email not found or access denied.'], 404);
        return; // Use return instead of exit for consistency
    }

    $parsed_content = null;

    // Check if parsed_content is valid JSON
    if (!empty($email['parsed_content'])) {
        $parsed_content = json_decode($email['parsed_content'], true);
        // json_decode returns null on error
        if ($parsed_content === null) {
            // Content is invalid, treat as missing and fallback to raw parsing
            error_log("Invalid JSON in parsed_content for email_id: $email_id");
        }
    }

    // If parsed_content is missing or was invalid, parse the raw email as a fallback
    if ($parsed_content === null) {
        if (empty($email['raw_email'])) {
             send_json_response(['status' => 'error', 'message' => 'No content available to display for this email.'], 404);
             return;
        }
        require_once __DIR__ . '/../utils/mime_parser.php';
        $parsed_content = parse_email_body($email['raw_email']);
    }

    send_json_response(['status' => 'success', 'data' => $parsed_content]);

} catch (PDOException $e) {
    error_log("Get email content error: " . $e->getMessage());
    send_json_response(['status' => 'error', 'message' => 'Failed to fetch email content.'], 500);
}
