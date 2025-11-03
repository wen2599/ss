<?php
// backend/handlers/get_log_file.php

// This is a protected handler, so $current_user_id is available from api.php

// For security, we only allow reading a specific, hardcoded log file.
// IMPORTANT: Never allow user input to determine the file path.
$log_file_path = __DIR__ . '/../../bot_errors.log'; // Assumes log is in the root

if (file_exists($log_file_path)) {
    $log_content = file_get_contents($log_file_path);
    if ($log_content === false) {
        send_json_response(['status' => 'error', 'message' => 'Failed to read log file.'], 500);
    } else {
        send_json_response(['status' => 'success', 'log_content' => $log_content]);
    }
} else {
    send_json_response(['status' => 'success', 'log_content' => 'Log file does not exist.']);
}
