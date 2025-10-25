<?php
// backend/bootstrap.php

// --- CORS Configuration ---
// This section handles Cross-Origin Resource Sharing (CORS) preflight requests and sets headers.

// Allow requests from your specific frontend origin.
// IMPORTANT: Replace * with your actual frontend domain in production, e.g., 'https://ss.wenxiuxiu.eu.org'
$allowed_origin = 'https://ss.wenxiuxiu.eu.org';

header("Access-Control-Allow-Origin: " . $allowed_origin);
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

// Handle the browser's preflight 'OPTIONS' request.
// This is crucial for CORS to work correctly with methods like POST.
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    // Respond with a 204 No Content status, indicating success and that no further action is needed.
    http_response_code(204);
    // Stop script execution, as this was just a preflight check.
    exit;
}

// --- Environment and Database Initialization ---

// Load environment variables (like database credentials)
require_once __DIR__ . '/load_env.php';

// Database connection global variable
$db_connection = null;

/**
 * Establishes a database connection using credentials from the environment.
 */
function connect_to_database() {
    global $db_connection;

    // Retrieve database credentials using getenv()
    $db_host = getenv('DB_HOST');
    $db_user = getenv('DB_USER');
    $db_pass = getenv('DB_PASS');
    $db_name = getenv('DB_NAME');

    // Check if credentials are set
    if (!$db_host || !$db_user || !$db_pass || !$db_name) {
        http_response_code(500);
        echo json_encode(["message" => "Database configuration is incomplete."]);
        exit;
    }

    // Create a new database connection
    $db_connection = new mysqli($db_host, $db_user, $db_pass, $db_name);

    // Check for connection errors
    if ($db_connection->connect_error) {
        http_response_code(500);
        echo json_encode(["message" => "Database connection failed: " . $db_connection->connect_error]);
        exit;
    }

    // Set character set to UTF-8
    $db_connection->set_charset("utf8mb4");
}

// --- JWT Helper Functions ---

// Include the JWT helper functions
require_once __DIR__ . '/api/jwt_helper.php';


// --- Global Execution ---

// Automatically connect to the database when this script is bootstrapped.
connect_to_database();
