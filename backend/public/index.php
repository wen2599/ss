<?php

// Simplified Front Controller

// 1. Load Core Libraries & Config
require_once __DIR__ . '/../src/core/Response.php';
require_once __DIR__ . '/../src/config.php';
require_once __DIR__ . '/../src/core/Database.php'; // Make DB functions available to all API handlers

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
