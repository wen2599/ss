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

$request_secret = $_SERVER['HTTP_X_WORKER_SECRET'] ?? '';
if ($request_secret !== $WORKER_SECRET) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

// 2. Validate incoming data
$user_email = $_GET['user_email'] ?? null;
if (!$user_email) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Missing user_email parameter']);
    exit;
}

// 3. Construct the user's directory path
$user_dir_name = preg_replace('/[^a-zA-Z0-9_.-]/', '_', $user_email);
$user_upload_dir = __DIR__ . '/uploads/' . $user_dir_name;

// 4. Check if the user's directory exists
if (!is_dir($user_upload_dir)) {
    echo json_encode(['success' => true, 'emails' => []]); // No emails for this user yet
    exit;
}

// 5. Scan for individual email directories and read their email.json files
$email_dirs = array_diff(scandir($user_upload_dir), ['.', '..']);
$emails = [];

foreach ($email_dirs as $email_id) {
    $email_dir_path = $user_upload_dir . '/' . $email_id;
    $json_filepath = $email_dir_path . '/email.json';

    if (is_dir($email_dir_path) && file_exists($json_filepath)) {
        $json_content = file_get_contents($json_filepath);
        $email_data = json_decode($json_content, true);

        if ($email_data) {
            // Add the unique email ID to the data object for reference
            $email_data['id'] = $email_id;
            $emails[] = $email_data;
        }
    }
}

// Optional: Sort emails by date (assuming a 'date' field exists in the headers)
usort($emails, function($a, $b) {
    $dateA = isset($a['headers']['date']) ? strtotime($a['headers']['date']) : 0;
    $dateB = isset($b['headers']['date']) ? strtotime($b['headers']['date']) : 0;
    return $dateB - $dateA; // Sort descending (newest first)
});

// 6. Return the emails as JSON
echo json_encode(['success' => true, 'emails' => $emails]);
?>