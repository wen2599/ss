<?php
// backend/install.php

// This script is designed to be run from the command line (CLI) to set up the database.
// Usage: php install.php

// --- Environment and Database Initialization ---
require_once __DIR__ . '/load_env.php';

$db_connection = null;

function connect_to_database_for_install()
{
    global $db_connection;

    $db_host = getenv('DB_HOST');
    $db_user = getenv('DB_USER');
    $db_pass = getenv('DB_PASS');
    $db_name = getenv('DB_NAME');

    if (! $db_host || ! $db_user || ! $db_pass || ! $db_name) {
        echo "❌ Error: Database configuration is incomplete. Please check your .env file.\n";
        exit(1);
    }

    $db_connection = new mysqli($db_host, $db_user, $db_pass, $db_name);

    if ($db_connection->connect_error) {
        echo "❌ Error: Database connection failed: " . $db_connection->connect_error . "\n";
        exit(1);
    }

    $db_connection->set_charset("utf8mb4");
}

// Set a flag to indicate CLI mode.
define('IS_CLI', true);

// Use echo for output in CLI mode.
echo "=========================================\n";
echo "        Database Setup Script        \n";
echo "=========================================\n\n";

// Connect to the database using our local function
connect_to_database_for_install();

// Check if the database connection was successful.
if (!$db_connection) {
    echo "❌ Error: Failed to connect to the database. Please check your .env file.\n";
    exit(1); // Exit with a non-zero status code to indicate failure.
}

echo "✅ Database connection successful.\n";

$sql_file_path = __DIR__ . '/setup.sql';
if (!file_exists($sql_file_path)) {
    echo "❌ Error: setup.sql file not found in the backend directory.\n";
    exit(1);
}

echo "✅ Found setup.sql file.\n";

// Read the entire SQL file.
$sql_commands = file_get_contents($sql_file_path);

// Remove comments and split into individual statements.
$sql_commands = preg_replace('/--.*/', '', $sql_commands);
$statements = explode(';', $sql_commands);

$db_connection->begin_transaction();
$total_statements = 0;
$executed_statements = 0;

try {
    foreach ($statements as $statement) {
        $trimmed_statement = trim($statement);
        if (!empty($trimmed_statement)) {
            $total_statements++;
            echo "Executing: " . substr($trimmed_statement, 0, 50) . "...\n";
            if ($db_connection->query($trimmed_statement) === false) {
                // If a statement fails, throw an exception to trigger the rollback.
                throw new Exception("Error executing statement: " . $db_connection->error);
            }
            $executed_statements++;
        }
    }

    // If all statements were successful, commit the transaction.
    $db_connection->commit();
    echo "\n=========================================\n";
    echo "✅ Database setup completed successfully!\n";
    echo "Executed {$executed_statements} out of {$total_statements} SQL statements.\n";
    echo "=========================================\n";

} catch (Exception $e) {
    // If any statement fails, roll back the entire transaction.
    $db_connection->rollback();
    echo "\n=========================================\n";
    echo "❌ An error occurred. Rolling back all changes.\n";
    echo "Error details: " . $e->getMessage() . "\n";
    echo "=========================================\n";
    exit(1);
}

// Close the database connection.
$db_connection->close();
exit(0); // Exit with a zero status code to indicate success.
