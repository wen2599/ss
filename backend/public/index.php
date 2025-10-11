<?php

// --- Bootstrap Application ---
// This single line loads all configurations, core libraries, and error handlers.
require_once __DIR__ . '/../src/config.php';

// --- API Routing ---
// Get the requested endpoint from the query string.
$endpoint = $_GET['endpoint'] ?? null;

if ($endpoint === 'telegramWebhook') {
    // The main entry point for all incoming Telegram updates.
    require_once __DIR__ . '/../src/api/telegramWebhook.php';
    // Explicitly send a success response to Telegram to acknowledge receipt.
    // This is crucial to prevent Telegram from resending updates and eventually disabling the webhook.
    Response::json(['status' => 'ok']);
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
