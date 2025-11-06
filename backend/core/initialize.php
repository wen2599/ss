<?php
// Set common headers
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *"); // For development, will be proxied in production
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

// Handle preflight requests for CORS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// Load environment variables
require_once __DIR__ . '/../utils/env_loader.php';
load_env(__DIR__ . '/../.env');

// Include helper functions
require_once __DIR__ . '/../utils/api_helpers.php';
require_once __DIR__ . '/auth.php';

// Establish database connection
require_once __DIR__ . '/database.php';
$pdo = get_db_connection();
