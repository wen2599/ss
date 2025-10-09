<?php

// --- Global Error & Exception Handling ---
// This block ensures that any error, warning, or notice in the application
// is caught and returned as a clean JSON response, preventing output pollution.

// Set a custom error handler
set_error_handler(function ($severity, $message, $file, $line) {
    // We must throw an exception to be caught by our exception handler.
    if (!(error_reporting() & $severity)) {
        // This error code is not included in error_reporting
        return;
    }
    throw new ErrorException($message, 0, $severity, $file, $line);
});

// Set a custom exception handler
set_exception_handler(function ($exception) {
    // Log the real error to the server's error log for debugging
    error_log($exception);

    // Send a generic, clean JSON error response to the client
    http_response_code(500);
    header('Content-Type: application/json');
    header('Access-Control-Allow-Origin: *'); // Ensure CORS headers are set
    echo json_encode([
        'error' => 'An internal server error occurred.',
        'detail' => [
            'message' => $exception->getMessage(),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
        ]
    ]);
    exit;
});

// --- Main Application Logic ---

// 1. Load Core Libraries & Config
require_once __DIR__ . '/../src/core/Response.php';
require_once __DIR__ . '/../src/config.php';
require_once __DIR__ . '/../src/core/Database.php';

// 2. Set global request body for POST/PUT requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' || $_SERVER['REQUEST_METHOD'] === 'PUT') {
    $GLOBALS['requestBody'] = json_decode(file_get_contents('php://input'), true);
}

// 3. Get the requested endpoint
$endpoint = $_GET['endpoint'] ?? null;

// 4. Route the request to the correct API handler
if ($endpoint) {
    $handlerPath = __DIR__ . '/../src/api/' . basename($endpoint) . '.php';

    if (file_exists($handlerPath)) {
        require $handlerPath;
    } else {
        Response::json(['error' => 'API endpoint not found'], 404);
    }
} else {
    Response::json(['error' => 'No API endpoint specified'], 400);
}