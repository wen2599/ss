<?php
// backend/debug_schema.php

ini_set('display_errors', 1);
error_reporting(E_ALL);

echo "<pre>";
echo "Starting schema debug...\n\n";

require_once __DIR__ . '/env_loader.php';
require_once __DIR__ . '/db_connection.php';

try {
    $conn = get_db_connection();
    echo "Database connection successful.\n\n";

    // --- Function to describe a table ---
    function describe_table($conn, $tableName) {
        echo "--- Describing table: `{$tableName}` ---\n";
        $result = $conn->query("DESCRIBE `{$tableName}`");
        if ($result && $result->num_rows > 0) {
            $fields = [];
            while ($row = $result->fetch_assoc()) {
                $fields[] = $row;
            }
            print_r($fields);
        } else {
            echo "Could not describe table or table is empty.\n";
            echo "Error: " . $conn->error . "\n";
        }
        echo "\n";
    }

    // --- Describe the tables ---
    describe_table($conn, 'users');
    describe_table($conn, 'emails');

    $conn->close();

} catch (Exception $e) {
    echo "--- An error occurred ---\n";
    echo "Message: " . $e->getMessage() . "\n";
}

echo "</pre>";

?>
