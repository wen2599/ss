<?php
// This endpoint handles the file uploads from the Cloudflare Worker.

// --- Security and Request Validation ---
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['success' => false, 'error' => 'Only POST method is allowed.'], 405);
}

// The worker sends the secret in the form data for this endpoint.
$worker_secret = $_POST['worker_secret'] ?? '';
if ($worker_secret !== $_ENV['WORKER_SECRET']) {
    json_response(['success' => false, 'error' => 'Unauthorized.'], 403);
}

$user_email = $_POST['user_email'] ?? null;
if (!$user_email || !filter_var($user_email, FILTER_VALIDATE_EMAIL)) {
    json_response(['success' => false, 'error' => 'Valid user email is required.'], 400);
}

// --- Directory and File Handling ---

// Sanitize the email to create a safe directory name
$user_dir_name = basename(preg_replace('/[^a-zA-Z0-9_.-]/', '_', $user_email));
$upload_base_dir = __DIR__ . '/../uploads';
$user_upload_dir = $upload_base_dir . '/' . $user_dir_name;

// Create the base and user-specific upload directories if they don't exist
if (!is_dir($upload_base_dir)) {
    mkdir($upload_base_dir, 0755);
}
if (!is_dir($user_upload_dir)) {
    mkdir($user_upload_dir, 0755);
}

$saved_files = [];

// --- Process Uploaded Files ---

// Handle the main email file
if (isset($_FILES['raw_email_file'])) {
    $file = $_FILES['raw_email_file'];
    $dest_path = $user_upload_dir . '/' . basename($file['name']);
    if (move_uploaded_file($file['tmp_name'], $dest_path)) {
        $saved_files[] = $dest_path;
    }
}

// Handle the HTML body file, if it exists
if (isset($_FILES['html_body'])) {
    $file = $_FILES['html_body'];
    $dest_path = $user_upload_dir . '/' . basename($file['name']);
    if (move_uploaded_file($file['tmp_name'], $dest_path)) {
        $saved_files[] = $dest_path;
    }
}

// Handle multiple attachments
if (isset($_FILES['attachment'])) {
    $attachments = $_FILES['attachment'];
    // Re-organize the $_FILES array for easier iteration
    $num_attachments = count($attachments['name']);
    for ($i = 0; $i < $num_attachments; $i++) {
        if ($attachments['error'][$i] === UPLOAD_ERR_OK) {
            $tmp_name = $attachments['tmp_name'][$i];
            $name = basename($attachments['name'][$i]);
            $dest_path = $user_upload_dir . '/' . $name;
            if (move_uploaded_file($tmp_name, $dest_path)) {
                $saved_files[] = $dest_path;
            }
        }
    }
}

if (empty($saved_files)) {
    json_response(['success' => false, 'error' => 'No files were uploaded or saved.'], 400);
}

json_response(['success' => true, 'message' => 'Files uploaded successfully.', 'files' => $saved_files], 200);
?>