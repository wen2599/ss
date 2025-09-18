<?php
// Centralized error handling and configuration
require_once __DIR__ . '/error_logger.php';
require_once __DIR__ . '/config.php';

// Register the global error and exception handlers.
// This will catch any fatal errors and log them / return a clean JSON response.
register_error_handlers();

// --- Headers ---
// Note: CORS headers are now handled by the Cloudflare Worker proxy.
// We will still set the content type to JSON as a default for all API responses.
header('Content-Type: application/json');

// --- Session ---
// Include session configuration, which should now work correctly
require_once __DIR__ . '/session_config.php';
session_start();

?>
