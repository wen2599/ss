<?php

// --- Bootstrap Application ---
// This single line loads all configurations, core libraries, and error handlers.
require_once dirname(__DIR__) . '/bootstrap.php';

// --- API Routing ---
// Get the requested endpoint from the query string.
$endpoint = $_GET['endpoint'] ?? null;

if ($endpoint) {
    // Sanitize the endpoint to prevent directory traversal attacks.
    $handlerPath = realpath(__DIR__ . '/../src/api/' . basename($endpoint) . '.php');

    // Ensure the handler file exists and is within the intended directory.
    if ($handlerPath && strpos($handlerPath, realpath(__DIR__ . '/../src/api')) === 0) {
        require $handlerPath;
    } else {
        Response::json(['error' => 'API endpoint not found'], 404);
    }
} else {
    Response::json(['error' => 'No API endpoint specified'], 400);
}