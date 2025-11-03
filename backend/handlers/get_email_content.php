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
    // Fetch the pre-parsed email content, ensuring it belongs to the current user
    $stmt = $pdo->prepare("SELECT parsed_content FROM user_emails WHERE id = ? AND user_id = ?");
    $stmt->execute([$email_id, $current_user_id]);
    $email = $stmt->fetch();

    if (!$email || empty($email['parsed_content'])) {
        // If the content is missing (e.g., for an old email before this feature was added),
        // we can return a message or even attempt a fallback to parse the raw_email here.
        // For now, we will just indicate it's not found.
        send_json_response(['status' => 'error', 'message' => 'Parsed email content not found or access denied.'], 404);
    }

    // The content is already a JSON string, so we just need to send it.
    // To avoid double-encoding, we decode it first and then let send_json_response re-encode it.
    $parsed_content = json_decode($email['parsed_content'], true);

    send_json_response(['status' => 'success', 'data' => $parsed_content]);

} catch (PDOException $e) {
    error_log("Get email content error: " . $e->getMessage());
    send_json_response(['status' => 'error', 'message' => 'Failed to fetch email content.'], 500);
}
