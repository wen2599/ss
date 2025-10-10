<?php

// --- Global Error & Exception Handling ---
ini_set('display_errors', 0);
error_reporting(E_ALL);

set_exception_handler(function ($exception) {
    error_log($exception);
    http_response_code(500);
    header('Content-Type: application/json');
    header('Access-Control-Allow-Origin: *');
    echo json_encode(['error' => 'Internal Server Error', 'message' => $exception->getMessage()]);
    exit;
});

set_error_handler(function ($severity, $message, $file, $line) {
    if (!(error_reporting() & $severity)) { return; }
    throw new ErrorException($message, 0, $severity, $file, $line);
});

// --- Main Application Logic ---

// 1. Load Core Libraries
require_once __DIR__ . '/../src/core/Response.php';
require_once __DIR__ . '/../src/config.php';
require_once __DIR__ . '/../src/core/Database.php';
require_once __DIR__ . '/../src/core/Telegram.php'; // Include the new Telegram utility

// 2. Set global request body for POST/PUT requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' || $_SERVER['REQUEST_METHOD'] === 'PUT') {
    $GLOBALS['requestBody'] = json_decode(file_get_contents('php://input'), true);
}

// 3. Route the request to the correct API handler
$endpoint = $_GET['endpoint'] ?? null;
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