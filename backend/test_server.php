<?php

// The full path to the log file.
$log_file = __DIR__ . '/server_test.log';

// Clear the log file for a fresh start with each test.
if (file_exists($log_file)) {
    unlink($log_file);
}

// Helper function to write to the log.
function write_to_log($message) {
    global $log_file;
    file_put_contents($log_file, date('[Y-m-d H:i:s] ') . print_r($message, true) . "\n", FILE_APPEND);
}

// --- Start Logging ---

write_to_log("--- test_server.php EXECUTED ---");

// Log the request method.
write_to_log("Request Method: " . ($_SERVER['REQUEST_METHOD'] ?? 'Not set'));

// Log all request headers.
write_to_log("--- Request Headers ---");
if (function_exists('getallheaders')) {
    write_to_log(getallheaders());
} else {
    // Fallback for environments where getallheaders is not available
    $headers = [];
    foreach ($_SERVER as $name => $value) {
        if (substr($name, 0, 5) == 'HTTP_') {
            $header_name = str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($name, 5)))));
            $headers[$header_name] = $value;
        }
    }
    write_to_log($headers);
}

// Log the raw POST body, which is the most critical part.
write_to_log("--- Raw POST Body (php://input) ---");
$raw_input = file_get_contents('php://input');

if ($raw_input === false) {
    write_to_log("ERROR: Could not read php://input stream.");
} elseif (empty($raw_input)) {
    write_to_log("EMPTY: The raw input stream was empty.");
} else {
    write_to_log("SUCCESS: Received the following data:");
    write_to_log($raw_input);
}

write_to_log("--- test_server.php FINISHED ---");

// Acknowledge receipt to the caller (e.g., Telegram)
http_response_code(200);
header('Content-Type: application/json');
echo json_encode(['status' => 'ok', 'message' => 'Log created successfully.']);

?>