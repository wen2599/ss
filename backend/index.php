<?php
require_once __DIR__ . '/init.php';

// Security check: Ensure requests come from our Cloudflare Worker
$request_secret = $_SERVER['HTTP_X_WORKER_SECRET'] ?? '';
if ($request_secret !== $_ENV['WORKER_SECRET']) {
    json_response(['error' => 'Unauthorized access.'], 403);
}

// Whitelist of allowed actions
$allowed_actions = [
    'register',
    'login',
    'logout',
    'check_session',
    'process_email',
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