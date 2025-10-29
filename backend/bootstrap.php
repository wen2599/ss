<?php

declare(strict_types=1);

// backend/bootstrap.php

// --- Error Reporting Configuration ---
ini_set('display_errors', '0'); // Keep errors hidden from public
ini_set('log_errors', '1');
$log_file = __DIR__ . '/debug.log';
ini_set('error_log', $log_file);
error_reporting(E_ALL);

// --- "Black Box" Fatal Error Catcher ---
register_shutdown_function(function () use ($log_file) {
    $error = error_get_last();
    // Check for a fatal error
    if ($error !== null && in_array($error['type'], [E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR])) {
        // Clear any potentially half-rendered output
        if (ob_get_length()) {
            ob_end_clean();
        }

        // Log the error in a structured way
        $log_message = sprintf(
            "[%s] FATAL ERROR: %s in %s on line %d\n",
            date('Y-m-d H:i:s'),
            $error['message'],
            $error['file'],
            $error['line']
        );
        file_put_contents($log_file, $log_message, FILE_APPEND);

        // Send a generic, clean JSON error to the client
        // This prevents the 502 by ensuring a valid HTTP response
        if (!headers_sent()) {
            header('Content-Type: application/json');
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'A critical server error occurred. The incident has been logged.'
            ]);
        }
    }
});

// Start output buffering to prevent accidental output from breaking JSON responses.
ob_start();

/*
// --- CORS Configuration (No longer needed with Cloudflare Worker) ---
if (isset($_SERVER['REQUEST_METHOD'])) {
    $allowed_origin = 'https://ss.wenxiuxiu.eu.org';

    header("Access-Control-Allow-Origin: " . $allowed_origin);
    header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
    header("Access-Control-Allow-Headers: Content-Type, Authorization");
    header("Access-Control-Allow-Credentials: true");

    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(204);
        exit;
    }
}
*/

// --- Environment and Database Initialization ---
require_once __DIR__ . '/load_env.php';

$db_connection = null;

function connect_to_database()
{
    global $db_connection;

    $db_host = getenv('DB_HOST');
    $db_user = getenv('DB_USER');
    $db_pass = getenv('DB_PASS');
    $db_name = getenv('DB_NAME');

    if (! $db_host || ! $db_user || ! $db_pass || ! $db_name) {
        http_response_code(500);
        echo json_encode(["message" => "Database configuration is incomplete."]);
        exit;
    }

    $db_connection = new mysqli($db_host, $db_user, $db_pass, $db_name);

    if ($db_connection->connect_error) {
        http_response_code(500);
        echo json_encode(["message" => "Database connection failed: " . $db_connection->connect_error]);
        exit;
    }

    $db_connection->set_charset("utf8mb4");
}

function initialize_database_if_needed()
{
    global $db_connection;

    try {
        // Check if the 'users' table exists as an indicator of initialization.
        $result = $db_connection->query("SELECT 1 FROM `users` LIMIT 1");
    } catch (mysqli_sql_exception $e) {
        // Suppress the error if the table doesn't exist, as we will create it.
        $result = false;
    }

    if ($result === false) {
        // Table does not exist, so run the setup script.
        $sql_file_path = __DIR__ . '/setup.sql';
        if (file_exists($sql_file_path)) {
            $sql_commands = file_get_contents($sql_file_path);

            // Remove comments and split into individual statements.
            $sql_commands = preg_replace('/--.*/', '', $sql_commands);
            $statements = explode(';', $sql_commands);

            foreach ($statements as $statement) {
                $trimmed_statement = trim($statement);
                if (!empty($trimmed_statement)) {
                    // Execute each statement.
                    if ($db_connection->query($trimmed_statement) === false) {
                        // If a statement fails, log the error and stop.
                        http_response_code(500);
                        echo json_encode(["message" => "Database initialization failed: " . $db_connection->error]);
                        exit;
                    }
                }
            }
        } else {
            http_response_code(500);
            echo json_encode(["message" => "setup.sql file not found."]);
            exit;
        }
    }
}

// --- Global Execution ---
connect_to_database();
initialize_database_if_needed();

ob_end_clean(); // Clean the output buffer at the end of bootstrap.
