<?php

// --- Application Bootstrap File ---
// This file should be included at the beginning of all entry-point scripts.

// --- Environment Variable Loading ---
/**
 * Loads environment variables from the .env file into the application.
 *
 * This function reads the .env file from the project root, parses it,
 * and loads the variables into `$_ENV` and `$_SERVER` superglobals.
 * This makes them accessible via `getenv()` and standard superglobals.
 * It's designed to be called once, at the very start of the application.
 *
 * @param string $path The absolute path to the .env file.
 */
function load_environment_variables($path) {
    if (!file_exists($path)) {
        // In a web context, we can't output directly. We should log this.
        error_log("FATAL: .env file not found at path: {$path}. Application cannot start.");
        // For web requests, it's better to show a generic error than a blank page.
        if (php_sapi_name() !== 'cli') {
            http_response_code(500);
            header('Content-Type: application/json');
            echo json_encode(['status' => 'error', 'message' => 'Internal Server Configuration Error.']);
        }
        exit(1); // Stop execution if config is missing.
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) {
            continue; // Skip comments.
        }

        list($name, $value) = explode('=', $line, 2);
        $name = trim($name);
        // Remove quotes from the value, if present.
        $value = trim($value, '"');

        // Load into both superglobals for broad compatibility.
        $_ENV[$name] = $value;
        $_SERVER[$name] = $value; // Makes it available to getenv()
        putenv("{$name}={$value}"); // For other libraries that might rely on putenv/getenv
    }
}

// Determine the root directory and load the .env file.
// __DIR__ is the `backend` directory, so we go one level up.
$dotenv_path = __DIR__ . '/../.env';
load_environment_variables($dotenv_path);


// --- JWT Configuration ---
// Use getenv() to fetch the secret key from the environment.
$jwt_secret = getenv('JWT_SECRET_KEY') ?: 'your-super-secret-and-long-key-that-no-one-knows';
define('JWT_SECRET_KEY', $jwt_secret);

// Define how long the token is valid for. Default to 24 hours.
$token_lifetime = getenv('JWT_TOKEN_LIFETIME') ?: 86400;
define('JWT_TOKEN_LIFETIME', (int)$token_lifetime);


// --- Core Utility Includes ---
// Require all essential helper files. The order can be important if there are dependencies.
require_once __DIR__ . '/db_operations.php';
require_once __DIR__ . '/jwt_handler.php';
require_once __DIR__ . '/telegram_helpers.php';
// Add any other core files that are widely used here.
// For example: require_once __DIR__ . '/ai_helpers.php';

?>