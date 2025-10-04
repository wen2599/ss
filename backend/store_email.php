<?php
header("Content-Type: application/json");

require_once __DIR__ . '/init.php';
load_env(__DIR__ . '/.env');

$WORKER_SECRET = $_ENV['WORKER_SECRET'] ?? null;

// 1. Authenticate the request
if (!$WORKER_SECRET) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Application is not configured: WORKER_SECRET is missing.']);
    exit;
}

$request_secret = $_POST['worker_secret'] ?? '';
if ($request_secret !== $WORKER_SECRET) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

// 2. Validate incoming data
$user_email = $_POST['user_email'] ?? null;
$email_data_json = $_POST['email_data'] ?? null;

if (!$user_email || !$email_data_json) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Missing user_email or email_data fields.']);
    exit;
}

// 3. Decode the JSON email data
$email_data = json_decode($email_data_json, true);
if (json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid JSON in email_data field.']);
    exit;
}

// 4. Prepare directories
$user_dir_name = preg_replace('/[^a-zA-Z0-9_.-]/', '_', $user_email);
$user_upload_dir = __DIR__ . '/uploads/' . $user_dir_name;

// Create a unique directory for each email to avoid conflicts
$email_id = time() . '_' . uniqid();
$email_storage_dir = $user_upload_dir . '/' . $email_id;
$attachments_dir = $email_storage_dir . '/attachments';

if (!is_dir($attachments_dir)) {
    // The `true` parameter creates nested directories if they don't exist.
    if (!mkdir($attachments_dir, 0755, true)) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Failed to create storage directory.']);
        exit;
    }
}

// 5. Save the structured email data as email.json
$json_filepath = $email_storage_dir . '/email.json';
if (file_put_contents($json_filepath, json_encode($email_data, JSON_PRETTY_PRINT)) === false) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Failed to write email metadata to file.']);
    exit;
}

// 6. Handle file uploads (attachments)
$saved_attachments = [];
if (isset($_FILES) && count($_FILES) > 0) {
    foreach ($_FILES as $file) {
        // Sanitize the filename to prevent security issues.
        $filename = basename($file['name']);
        $dest_path = $attachments_dir . '/' . $filename;

        if (move_uploaded_file($file['tmp_name'], $dest_path)) {
            $saved_attachments[] = $filename;
        }
        // Silently ignore failed uploads for now, but in a real app, this should be logged.
    }
}

// 7. Send success response
echo json_encode([
    'success' => true,
    'message' => 'Email and attachments stored successfully.',
    'email_id' => $email_id,
    'saved_attachments' => $saved_attachments
]);
?>