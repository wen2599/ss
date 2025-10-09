<?php
// backend/index.php

// Include the bootstrap file to initialize the application
require_once __DIR__ . '/bootstrap.php';

// Get the requested endpoint from the query string
$endpoint = $_GET['endpoint'] ?? '';

// Define the path to the endpoints directory
$endpointsDir = __DIR__ . '/endpoints/';

// Sanitize the endpoint name to prevent directory traversal
$endpointFile = $endpointsDir . basename($endpoint) . '.php';

// Check if the endpoint file exists and include it
if ($endpoint && file_exists($endpointFile)) {
    require_once $endpointFile;
} else {
    // Handle the case where the endpoint is not found
    http_response_code(404);
    send_json_response(['error' => 'Endpoint not found']);
}