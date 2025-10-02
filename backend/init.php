<?php
// init.php
// This file is included at the beginning of all action scripts.

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Set the content type to JSON for all API responses
header('Content-Type: application/json');

// Start the session
session_start();

// Include the database configuration
require_once __DIR__ . '/config.php';

// Set a global error handler to catch any uncaught exceptions
set_exception_handler(function($exception) {
    // Log the full error
    error_log("Uncaught exception: " . $exception->getMessage() . "\n" . $exception->getTraceAsString());

    // Send a generic error response to the client
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'An unexpected server error occurred.'
    ]);
    exit;
});
