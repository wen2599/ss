<?php
// backend/migrate_add_userid_to_emails.php

ini_set('display_errors', 1);
error_reporting(E_ALL);

echo "<pre>";
echo "Starting migration...\n";

require_once __DIR__ . '/env_loader.php';
require_once __DIR__ . '/db_connection.php';

try {
    $conn = get_db_connection();
    echo "Database connection successful.\n";

    // --- Check if the column already exists ---
    $result = $conn->query("SHOW COLUMNS FROM `emails` LIKE 'user_id'");
    if ($result && $result->num_rows > 0) {
        echo "Migration already applied. The 'user_id' column already exists in the 'emails' table.\n";
    } else {
        // --- Add the user_id column ---
        echo "Adding 'user_id' column to 'emails' table...\n";
        $sql = "ALTER TABLE `emails` ADD COLUMN `user_id` INT NULL AFTER `id`, ADD INDEX `idx_user_id` (`user_id`)";

        if ($conn->query($sql) === TRUE) {
            echo "SUCCESS: The 'user_id' column was added to the 'emails' table successfully.\n";
        } else {
            echo "ERROR: Failed to add the 'user_id' column. Error: " . $conn->error . "\n";
        }
    }

    $conn->close();

} catch (Exception $e) {
    echo "--- An error occurred during migration ---\n";
    echo "Message: " . $e->getMessage() . "\n";
}

echo "</pre>";

?>
