<?php
// backend/install.php

// This script is designed to be run from the command line (CLI) to set up the database.
// Usage: php install.php

// Set a flag to indicate CLI mode, which can be used in bootstrap.php if needed.
define('IS_CLI', true);

// Use echo for output in CLI mode.
echo "=========================================\n";
echo "        Database Setup Script        \n";
echo "=========================================\n\n";

// We need the bootstrap file for the database connection.
require_once __DIR__ . '/bootstrap.php';

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
