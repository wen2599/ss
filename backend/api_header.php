<?php
// backend/api_header.php

// Set a consistent JSON content type for all API responses.
header('Content-Type: application/json');

// Start or resume the session to access user authentication data.
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Ensure errors are handled, and config is loaded.
require_once __DIR__ . '/config.php';
