<?php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// --- Bootstrap Application ---
// This single line loads all configurations, core libraries, and error handlers.
require_once __DIR__ . '/../src/config.php';

// --- API Routing ---
// Get the requested endpoint from the query string.
$endpoint = $_GET['endpoint'] ?? null;

if ($endpoint === 'telegramWebhook') {
    // The main entry point for all incoming Telegram updates.
    // The included script is responsible for handling everything, including the final response.
    require_once __DIR__ . '/../src/api/telegramWebhook.php';

} elseif ($endpoint) {
    // Support for other potential, non-Telegram API endpoints.
    $handlerPath = realpath(__DIR__ . '/../src/api/' . basename($endpoint) . '.php');

    if ($handlerPath && strpos($handlerPath, realpath(__DIR__ . '/../src/api')) === 0) {
        require $handlerPath;
    } else {
        Response::json(['error' => 'API endpoint not found'], 404);
    }
} else {
    Response::json(['error' => 'No API endpoint specified'], 400);
}