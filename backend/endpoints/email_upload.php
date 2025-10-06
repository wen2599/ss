<?php
// backend/endpoints/email_upload.php

// Bootstrap the application
require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/helpers.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    send_json_response(['error' => 'Method not allowed.'], 405);
}

// 1. Validate Worker Secret
$worker_secret = $_POST['worker_secret'] ?? '';
if ($worker_secret !== WORKER_SECRET) {
    send_json_response(['error' => 'Unauthorized.'], 401);
}

// 2. Get User Email and Create User-Specific Upload Directory
$user_email = $_POST['user_email'] ?? '';
if (empty($user_email) || !filter_var($user_email, FILTER_VALIDATE_EMAIL)) {
    send_json_response(['error' => 'Invalid user email provided.'], 400);
}

// Sanitize the email to create a safe directory name
$user_dir_name = preg_replace('/[^a-zA-Z0-9_.-]/', '_', $user_email);
$user_upload_dir = UPLOADS_DIR . '/' . $user_dir_name;

if (!is_dir($user_upload_dir) && !mkdir($user_upload_dir, 0755, true)) {
    send_json_response(['error' => 'Could not create user upload directory.'], 500);
}

// 3. Process and Save Uploaded Files
$upload_errors = [];

// Handle the raw email body
if (isset($_FILES['raw_email_file'])) {
    $file = $_FILES['raw_email_file'];
    $filename = basename($file['name']);
    $dest_path = $user_upload_dir . '/' . $filename;
    if (!move_uploaded_file($file['tmp_name'], $dest_path)) {
        $upload_errors[] = "Failed to save raw email file: {$filename}";
    }
} else {
    $upload_errors[] = "Raw email file is missing.";
}

// Handle the HTML email body (optional)
if (isset($_FILES['html_body'])) {
    $file = $_FILES['html_body'];
    $filename = basename($file['name']);
    $dest_path = $user_upload_dir . '/' . $filename;
    if (!move_uploaded_file($file['tmp_name'], $dest_path)) {
        $upload_errors[] = "Failed to save HTML body: {$filename}";
    }
}

// Handle attachments (optional, multiple)
if (isset($_FILES['attachment'])) {
    $attachments = $_FILES['attachment'];
    // Normalize the array structure if only one attachment is sent
    $files_to_process = is_array($attachments['name']) ?
        array_map(function ($name, $type, $tmp_name, $error, $size) {
            return ['name' => $name, 'type' => $type, 'tmp_name' => $tmp_name, 'error' => $error, 'size' => $size];
        }, $attachments['name'], $attachments['type'], $attachments['tmp_name'], $attachments['error'], $attachments['size']) :
        [$attachments];

    foreach ($files_to_process as $file) {
        if ($file['error'] === UPLOAD_ERR_OK) {
            $filename = basename($file['name']);
            $dest_path = $user_upload_dir . '/' . $filename;
            if (!move_uploaded_file($file['tmp_name'], $dest_path)) {
                $upload_errors[] = "Failed to save attachment: {$filename}";
            }
        }
    }
}

// 4. Send Final Response
if (!empty($upload_errors)) {
    send_json_response(['error' => 'There were errors during the upload.', 'details' => $upload_errors], 500);
} else {
    send_json_response(['success' => true, 'message' => 'Email and attachments uploaded successfully.']);
}
?>