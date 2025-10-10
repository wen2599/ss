<?php

// --- Global Error & Exception Handling ---
// This block ensures that any error, warning, or notice in the application
// is caught and returned as a clean JSON response, preventing output pollution.
ini_set('display_errors', 0); // Do not display errors directly to the user
error_reporting(E_ALL);

set_exception_handler(function ($exception) {
    error_log($exception); // Log the real error for debugging
    http_response_code(500);
    header('Content-Type: application/json');
    header('Access-Control-Allow-Origin: *'); // Ensure CORS on error
    echo json_encode([
        'error' => 'An internal server error occurred.',
        'message' => $exception->getMessage()
    ]);
    exit;
});

set_error_handler(function ($severity, $message, $file, $line) {
    if (!(error_reporting() & $severity)) { return; }
    throw new ErrorException($message, 0, $severity, $file, $line);
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