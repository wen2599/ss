<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(204);
    exit;
}

/**
 * api.php
 *
 * This script serves as the primary API endpoint for the Cloudflare Worker.
 * It handles the uploading of email content sent as a file.
 *
 * --- How It Works ---
 * 1. It expects a POST request with 'multipart/form-data'.
 * 2. It requires three fields: 'worker_secret', 'user_email', and a file upload named 'chat_file'.
 * 3. It validates the secret to ensure the request is from a trusted source.
 * 4. It checks if the upload directory exists and is writable, creating it if necessary.
 * 5. It creates a subdirectory based on the user's email for organized storage.
 * 6. It saves the uploaded file to the user-specific directory with a unique timestamped name.
 * 7. It returns a JSON response indicating success or failure.
 */

// 1. Include Configuration using an absolute path
require_once __DIR__ . '/config.php';

// 2. Set Headers
header('Content-Type: application/json');

// 3. Security Check: Validate the Worker Secret from POST data
if (!isset($_POST['worker_secret']) || $_POST['worker_secret'] !== $worker_secret) {
    http_response_code(403); // Forbidden
    echo json_encode(['success' => false, 'error' => 'Access denied. Invalid secret.']);
    exit();
}

// 4. Input Validation
if (!isset($_POST['user_email']) || !filter_var($_POST['user_email'], FILTER_VALIDATE_EMAIL)) {
    http_response_code(400); // Bad Request
    echo json_encode(['success' => false, 'error' => 'Invalid or missing user_email.']);
    exit();
}

if (!isset($_FILES['chat_file']) || $_FILES['chat_file']['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400); // Bad Request
    $upload_errors = [
        UPLOAD_ERR_INI_SIZE   => "The uploaded file exceeds the upload_max_filesize directive in php.ini.",
        UPLOAD_ERR_FORM_SIZE  => "The uploaded file exceeds the MAX_FILE_SIZE directive that was specified in the HTML form.",
        UPLOAD_ERR_PARTIAL    => "The uploaded file was only partially uploaded.",
        UPLOAD_ERR_NO_FILE    => "No file was uploaded.",
        UPLOAD_ERR_NO_TMP_DIR => "Missing a temporary folder.",
        UPLOAD_ERR_CANT_WRITE => "Failed to write file to disk.",
        UPLOAD_ERR_EXTENSION  => "A PHP extension stopped the file upload.",
    ];
    $error_message = isset($upload_errors[$_FILES['chat_file']['error']]) 
        ? $upload_errors[$_FILES['chat_file']['error']] 
        : 'Unknown upload error.';
    echo json_encode(['success' => false, 'error' => $error_message]);
    exit();
}

// 5. Directory and File Handling
$user_email = $_POST['user_email'];
$file_tmp_path = $_FILES['chat_file']['tmp_name'];
$file_name = $_FILES['chat_file']['name'];

// Sanitize email to create a safe directory name (e.g., 'user_example_com')
$user_dir_name = preg_replace('/[^a-zA-Z0-9_-]/', '_', $user_email);
$user_upload_dir = UPLOAD_DIR . $user_dir_name . '/';

// Create the main upload directory if it doesn't exist
if (!is_dir(UPLOAD_DIR) && !mkdir(UPLOAD_DIR, 0755)) {
    http_response_code(500);
    error_log("Failed to create main upload directory: " . UPLOAD_DIR);
    echo json_encode(['success' => false, 'error' => 'Server configuration error: Cannot create upload directory.']);
    exit();
}

// Create the user-specific directory if it doesn't exist
if (!is_dir($user_upload_dir) && !mkdir($user_upload_dir, 0755, true)) {
    http_response_code(500);
    error_log("Failed to create user directory: " . $user_upload_dir);
    echo json_encode(['success' => false, 'error' => 'Server configuration error: Cannot create user directory.']);
    exit();
}

// Generate a unique filename to prevent overwrites
$sanitized_file_name = basename($file_name);
$destination_path = $user_upload_dir . date('Y-m-d_H-i-s') . '_' . $sanitized_file_name;

// 6. Move the Uploaded File
if (move_uploaded_file($file_tmp_path, $destination_path)) {
    // 7. Send Success Response
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => 'File uploaded successfully.',
        'path' => $destination_path
    ]);
} else {
    http_response_code(500);
    error_log("Failed to move uploaded file to: " . $destination_path);
    echo json_encode(['success' => false, 'error' => 'Failed to save the uploaded file.']);
}

?>
