<?php

// --- Global Error & Exception Handling ---
// This ensures that any error, warning, or notice in the application
// is caught and returned as a clean JSON response, preventing output pollution.
ini_set('display_errors', 1);
error_reporting(E_ALL);

set_exception_handler(function ($exception) {
    error_log($exception);
    http_response_code(500);
    header('Content-Type: application/json');
    header('Access-Control-Allow-Origin: *');
    echo json_encode([
        'error' => 'Internal Server Error',
        'message' => $exception->getMessage()
    ]);
    exit;
});

// 1. Load Core Libraries & Config
// The paths are relative to this index.php file.
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