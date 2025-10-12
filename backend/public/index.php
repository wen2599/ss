<?php
// =================================================================
// FOR DEBUGGING PURPOSES: Force display of all errors.
// This will expose the root cause of the 502 Bad Gateway error.
// =================================================================
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Allow requests from the specific frontend origin.
header("Access-Control-Allow-Origin: https://ss.wenxiuxiu.eu.org");
// Allow credentials (cookies, authorization headers, etc.) to be sent.
header("Access-Control-Allow-Credentials: true");
// Specify which methods are allowed for CORS requests.
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
// Specify which headers are allowed for CORS requests.
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");

// Handle preflight OPTIONS requests.
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    // End script execution for preflight requests.
    exit(0);
}

// Start session to manage user login state.
session_start();

// Autoload all Composer dependencies.
require_once __DIR__ . '/../vendor/autoload.php';

// Set the default content type for all API responses to JSON.
header('Content-Type: application/json');

// Define the base path for API endpoint files.
$apiBasePath = __DIR__ . '/../src/api/';

// Get the requested endpoint from the query string.
$endpoint = $_GET['endpoint'] ?? '';

// Sanitize the endpoint name to prevent directory traversal attacks.
$endpoint = basename($endpoint);

// Construct the full path to the endpoint file.
// This logic correctly handles cases where the endpoint might or might not have the .php extension.
$filePath = $apiBasePath . $endpoint;
if (!str_ends_with($filePath, '.php')) {
    $filePath .= '.php';
}

// If the endpoint file exists, include it to handle the request.
// Otherwise, return a 404 Not Found error.
if (file_exists($filePath)) {
    try {
        require_once $filePath;
    } catch (Exception $e) {
        // Return a generic 500 server error if the endpoint logic fails.
        http_response_code(500);
        echo json_encode(['error' => 'Internal Server Error: ' . $e->getMessage()]);
    }
} else {
    // Return a 404 error if the endpoint file does not exist.
    http_response_code(404);
    echo json_encode(['error' => "Endpoint '{$endpoint}' not found."]);
}
