<?php
/**
 * Front Controller for the entire API.
 *
 * This script routes all incoming API requests to the appropriate handler file.
 * It ensures a consistent entry point, handles basic security checks, and
 * provides a centralized location for bootstrapping the application.
 */

// Bootstrap the application by loading configuration and helpers.
// This ensures the environment, database connection, and helper functions are available globally.
require_once __DIR__ . '/config.php';

// Set a default content type for all API responses.
header('Content-Type: application/json');

// --- Endpoint Routing ---

// 1. Get the requested endpoint from the query string (set by .htaccess).
$endpoint = $_GET['endpoint'] ?? '';

// 2. Sanitize the endpoint to prevent directory traversal attacks.
// This removes any characters that are not alphanumeric, underscore, or hyphen.
$endpoint = preg_replace('/[^a-zA-Z0-9_-]/', '', $endpoint);

// 3. Construct the full path to the potential handler file.
$file_path = __DIR__ . '/' . $endpoint . '.php';

// 4. Check if the handler file exists and is readable.
if (!empty($endpoint) && file_exists($file_path) && is_readable($file_path)) {
    // If the file exists, include it. The included file is expected to handle
    // the rest of the request processing and output a JSON response.
    require_once $file_path;
} else {
    // 5. If the endpoint is missing or the file doesn't exist, return a 404 Not Found error.
    http_response_code(404);
    echo json_encode([
        'error' => 'Endpoint not found.',
        'requested' => $endpoint // Helps in debugging
    ]);
}

?>