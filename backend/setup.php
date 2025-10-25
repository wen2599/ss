<?php
// backend/setup.php

// This script is designed to be run from the command line (CLI) via SSH.
// It connects to the database and creates the necessary 'lottery_draws' table.

// --- Main Execution Logic ---

// Set the timezone to avoid potential warnings
date_default_timezone_set('UTC');

// Define the expected path for the .env file (one directory up from this script)
$env_path = __DIR__ . '/../.env';

/**
 * Loads environment variables from a .env file.
 * @param string $path The path to the .env file.
 * @throws Exception if the file is not found.
 */
function load_environment_variables($path)
{
    if (!file_exists($path) || !is_readable($path)) {
        throw new Exception("Error: .env file not found or is not readable at '{$path}'.");
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        // Skip comments
        if (strpos(trim($line), '#') === 0) {
            continue;
        }

        // Split into name and value
        list($name, $value) = explode('=', $line, 2);
        $name = trim($name);
        $value = trim($value);

        // Set environment variable
        if (!empty($name)) {
            putenv("{$name}={$value}");
            $_ENV[$name] = $value;
            $_SERVER[$name] = $value;
        }
    }
}

try {
    echo "[INFO] Starting database setup...\n";

    // 1. Load environment variables
    load_environment_variables($env_path);
    echo "[OK] Loaded environment variables from: {$env_path}\n";

    // 2. Get database credentials from environment
    $db_host = getenv('DB_HOST');
    $db_name = getenv('DB_DATABASE');
    $db_user = getenv('DB_USERNAME');
    $db_pass = getenv('DB_PASSWORD');

    if (empty($db_host) || empty($db_name) || empty($db_user)) {
        throw new Exception("Error: Database credentials (DB_HOST, DB_DATABASE, DB_USERNAME) are not fully set in the .env file.");
    }

    echo "[INFO] Connecting to database '{$db_name}' on host '{$db_host}'...\n";

    // 3. Establish database connection
    $conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
    if ($conn->connect_error) {
        throw new Exception("Connection failed: " . $conn->connect_error);
    }
    echo "[OK] Database connection successful.\n";

    // 4. Define the SQL for creating the table
    $sql = "
    CREATE TABLE IF NOT EXISTS lottery_draws (
        id INT AUTO_INCREMENT PRIMARY KEY,
        draw_date DATE NOT NULL,
        draw_period VARCHAR(255) NOT NULL,
        numbers VARCHAR(255) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY (draw_period)
    ) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;";

    echo "[INFO] Executing SQL to create 'lottery_draws' table...\n";

    // 5. Execute the query
    if ($conn->query($sql) === TRUE) {
        echo "[OK] Table 'lottery_draws' created successfully or already exists.\n";
    } else {
        throw new Exception("Error creating table: " . $conn->error);
    }

    // 6. Close the connection
    $conn->close();
    echo "[SUCCESS] Database setup is complete.\n";

} catch (Exception $e) {
    // Catch any errors and display them
    echo "[FATAL] An error occurred during setup:\n";
    echo $e->getMessage() . "\n";
    exit(1); // Exit with a non-zero status code to indicate failure
}

exit(0); // Exit with status 0 for success
