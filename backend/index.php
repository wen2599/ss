<?php
require_once __DIR__ . '/init.php';

// Security check: Allow secret via header (for user actions) or GET param (for worker actions)
$worker_secret = $_ENV['WORKER_SECRET'];
$request_secret_header = $_SERVER['HTTP_X_WORKER_SECRET'] ?? '';
$request_secret_param = $_GET['worker_secret'] ?? '';

if ($request_secret_header !== $worker_secret && $request_secret_param !== $worker_secret) {
    json_response(['error' => 'Unauthorized access.'], 403);
}

// Whitelist of allowed actions
$allowed_actions = [
    'register',
    'login',
    'logout',
    'check_session',
    'process_email',
    'is_user_registered',
    'email_upload',
];

$action = $_GET['action'] ?? '';

// Validate if the action is in the whitelist
if (!in_array($action, $allowed_actions)) {
    json_response(['error' => 'Invalid action specified.'], 400);
}

// Construct the path to the action file
$action_file = __DIR__ . '/actions/' . $action . '.php';

// Execute the action file if it exists
if (file_exists($action_file)) {
    // Before including the action, create the 'actions' directory if it doesn't exist.
    // This is a safeguard, although the plan assumes it will be created.
    if (!is_dir(__DIR__ . '/actions')) {
        mkdir(__DIR__ . '/actions');
    }
    require_once $action_file;
} else {
    json_response(['error' => "Action handler '{$action}.php' not found."], 404);
}
?>