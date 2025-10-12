<?php
// This script initializes the 'emails' table in the database.
// It should be run once during setup.

require_once 'config.php';

try {
    $pdo = get_db_connection();
    if (!$pdo) {
        throw new Exception("Failed to connect to the database.");
    }

    // Read the SQL command from our schema file.
    $sql = file_get_contents('create_emails_table.sql');

    if ($sql === false) {
        throw new Exception("Could not read the create_emails_table.sql file.");
    }

    // Execute the SQL to create the table.
    $pdo->exec($sql);

    echo "Successfully initialized the 'emails' table.\n";

} catch (Exception $e) {
    // Provide a more informative error message.
    echo "An error occurred during table initialization: " . $e->getMessage() . "\n";
    // Exit with a non-zero status code to indicate failure, useful for scripting.
    exit(1);
}
?>