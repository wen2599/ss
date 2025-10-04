<?php
require_once __DIR__ . '/init.php';

// Security check: Allow secret via header, GET param, or POST body
$worker_secret = $_ENV['WORKER_SECRET'];
$request_secret_header = $_SERVER['HTTP_X_WORKER_SECRET'] ?? '';
$request_secret_param = $_GET['worker_secret'] ?? '';
$request_secret_post = $_POST['worker_secret'] ?? '';

// Whitelist of actions allowed to use the worker_secret via GET param or POST body
$worker_actions = ['email_upload', 'process_email', 'is_user_registered'];
$action = $_GET['action'] ?? $_POST['action'] ?? '';

// Refined security validation
$is_authorized = false;
if ($request_secret_header === $worker_secret) {
    $is_authorized = true;
} elseif (in_array($action, $worker_actions) && ($request_secret_param === $worker_secret || $request_secret_post === $worker_secret)) {
    $is_authorized = true;
}

if (!$is_authorized) {
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