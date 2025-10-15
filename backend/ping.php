<?php

// A simple script to log incoming requests to a file.
// This is to test if the webhook is being triggered at all.

try {
    // Define the log file path.
    $logFile = __DIR__ . '/ping.log';

    // Get the current date and time.
    $timestamp = date('Y-m-d H:i:s T');

    // Get all request headers.
    $headers = getallheaders();
    $headersJson = json_encode($headers, JSON_PRETTY_PRINT);

    // Get the raw request body.
    $body = file_get_contents('php://input');

    // Prepare the log entry.
    $logEntry = "---
Timestamp: {$timestamp}
Headers:
{$headersJson}
---
Raw Body:
{$body}
---
\n\n";

    // Append the entry to the log file.
    // The FILE_APPEND flag is crucial. LOCK_EX is for safe writing.
    $result = file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);

    if ($result === false) {
        // If we can't even write to the file, we can't log the error there.
        // We will try to send a very basic, hardcoded error response.
        http_response_code(500);
        echo "Error: Could not write to log file. Check file permissions for the directory.";
    } else {
        // Acknowledge receipt of the request.
        http_response_code(200);
        echo "Request logged successfully.";
    }

} catch (Throwable $e) {
    // Catch any fatal error during execution.
    // This is a last resort to try and report a problem.
    http_response_code(500);
    echo "A fatal error occurred: " . $e->getMessage();
}

?>