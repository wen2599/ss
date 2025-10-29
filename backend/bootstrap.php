<?php

declare(strict_types=1);

// backend/bootstrap.php


// --- CORS Configuration ---
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

// --- JWT Helper Functions ---
require_once __DIR__ . '/api/jwt_helper.php';

// --- Global Execution ---
connect_to_database();
initialize_database_if_needed();
