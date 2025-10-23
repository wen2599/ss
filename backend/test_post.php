<?php
// test_post.php
// A simple diagnostic script to determine how the server handles request methods.

header('Content-Type: text/plain');

$method = $_SERVER['REQUEST_METHOD'] ?? 'UNKNOWN';

echo "Script test_post.php executed successfully.\n";
echo "Timestamp: " . date('Y-m-d H:i:s') . "\n";
echo "Request Method Detected: " . $method . "\n";

if ($method === 'POST') {
    echo "SUCCESS: The server correctly received a POST request.\n";
} elseif ($method === 'GET') {
    echo "INFO: The server received a GET request. Please test with a POST request.\n";
} else {
    echo "WARNING: An unexpected request method was received.\n";
}
