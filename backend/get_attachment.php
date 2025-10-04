<?php
// This script securely serves a specific attachment file.

// 1. Validate incoming data
$user_email = $_GET['user_email'] ?? null;
$email_id = $_GET['email_id'] ?? null;
$filename = $_GET['filename'] ?? null;

if (!$user_email || !$email_id || !$filename) {
    http_response_code(400);
    echo "Error: Missing required parameters (user_email, email_id, filename).";
    exit;
}

// 2. Security: Sanitize all inputs to prevent directory traversal.
// Use basename() on the filename to strip any directory information. This is the most critical step.
$safe_filename = basename($filename);
// Also sanitize the other path components as a defense-in-depth measure.
$user_dir_name = preg_replace('/[^a-zA-Z0-9_.-]/', '_', $user_email);
$safe_email_id = preg_replace('/[^a-zA-Z0-9_]/', '', $email_id);

// 3. Construct the full, safe path to the attachment.
$filepath = __DIR__ . '/uploads/' . $user_dir_name . '/' . $safe_email_id . '/attachments/' . $safe_filename;

// 4. Check if the file exists and is readable.
if (!file_exists($filepath) || !is_readable($filepath)) {
    http_response_code(404);
    echo "Error: File not found.";
    exit;
}

// 5. Serve the file with appropriate headers.
// Determine the MIME type to ensure the browser handles the file correctly.
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mime_type = finfo_file($finfo, $filepath);
finfo_close($finfo);

// Set headers to prompt a download.
header('Content-Type: ' . $mime_type);
header('Content-Disposition: attachment; filename="' . $safe_filename . '"');
header('Content-Length: ' . filesize($filepath));
header('X-Content-Type-Options: nosniff'); // Security best practice

// Clear output buffer and send the file.
ob_clean();
flush();
readfile($filepath);
exit;
?>