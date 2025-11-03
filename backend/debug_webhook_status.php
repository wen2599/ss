<?php
// --- Webhook Debugger ---

// Set the timezone for accurate logging
date_default_timezone_set('UTC');

// The log file path
$log_file = __DIR__ . '/webhook_debug.log';

// --- Capture Request Data ---

// 1. Get the raw request body
$raw_input = file_get_contents('php://input');

// 2. Get request headers
$headers = getallheaders();

// 3. Get the request method and URI
$request_method = $_SERVER['REQUEST_METHOD'] ?? 'N/A';
$request_uri = $_SERVER['REQUEST_URI'] ?? 'N/A';
$remote_addr = $_SERVER['REMOTE_ADDR'] ?? 'N/A';

// --- Format the Log Entry ---

$log_entry = "--- [" . date('Y-m-d H:i:s T') . "] ---\n";
$log_entry .= "From IP: " . $remote_addr . "\n";
$log_entry .= "Request: " . $request_method . " " . $request_uri . "\n";
$log_entry .= "Headers: " . json_encode($headers, JSON_PRETTY_PRINT) . "\n";
$log_entry .= "Raw Body:\n";
$log_entry .= $raw_input . "\n";
$log_entry .= "--------------------------------------\n\n";

// --- Write to Log File ---

// Use file_put_contents with the FILE_APPEND flag to add to the log
// and LOCK_EX to prevent race conditions.
file_put_contents($log_file, $log_entry, FILE_APPEND | LOCK_EX);

// --- Respond to Telegram ---

// Always send a 200 OK response to Telegram to acknowledge receipt.
// This prevents Telegram from resending the update.
http_response_code(200);
header('Content-Type: application/json');
echo json_encode(['success' => true, 'message' => 'Request logged.']);

?>
