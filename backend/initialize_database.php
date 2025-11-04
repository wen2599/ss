<?php

// This script should be run from the command line to set up the database tables.

require_once __DIR__ . '/config.php';

echo "--- Database Initialization Script ---\n\n";

// 1. Get Database Connection
echo "Step 1: Connecting to the database...\n";
$pdo = get_db_connection();
if (!$pdo) {
    echo "[FAILURE] Could not connect to the database. Please check your .env credentials.\n";
    exit(1);
}
echo "  [SUCCESS] Database connection established.\n\n";

// 2. Read the SQL Schema File
echo "Step 2: Reading the database schema file (database_schema.sql)...\n";
$sql_file = __DIR__ . '/database_schema.sql';
if (!file_exists($sql_file)) {
    echo "[FAILURE] `database_schema.sql` not found in the backend directory.\n";
    exit(1);
}
$sql_content = file_get_contents($sql_file);
if (empty($sql_content)) {
    echo "[FAILURE] `database_schema.sql` is empty.\n";
    exit(1);
}
echo "  [SUCCESS] SQL schema file read successfully.\n\n";

// 3. Parse and Execute SQL Statements Individually
echo "Step 3: Executing SQL to create/update tables...\n";

// Remove comments and split into individual statements
$sql_content = preg_replace('/--.*/', '', $sql_content); // Remove -- comments
$sql_statements = explode(';', $sql_content);

$total_statements = 0;
$successful_statements = 0;

foreach ($sql_statements as $statement) {
    $statement = trim($statement);
    if (empty($statement)) {
        continue;
    }
    $total_statements++;

    // Extract table name for logging
    $table_name = 'N/A';
    if (preg_match('/CREATE TABLE IF NOT EXISTS `(.*?)`/', $statement, $matches)) {
        $table_name = $matches[1];
    }

    echo "  -> Executing query for table `{$table_name}`...\n";

    try {
        $pdo->exec($statement);
        echo "     [SUCCESS] Statement executed successfully.\n";
        $successful_statements++;
    } catch (PDOException $e) {
        echo "     [FAILURE] An error occurred.\n";
        echo "       Error: " . $e->getMessage() . "\n";
    }
}

echo "\nStep 3 Complete: {$successful_statements} out of {$total_statements} statements executed successfully.\n\n";

echo "--- Database Initialization Complete! ---\n";

?>