<?php
// A one-time script to set up the database schema.
// From your project root (e.g., public_html), run: php backend/setup_database.php

// --- Change Directory ---
// This ensures that all relative paths are resolved correctly from the script's location.
chdir(__DIR__);

// --- Bootstrap Application ---
// This single line loads all configurations, core libraries, and error handlers.
require_once __DIR__ . '/src/config.php';

// --- VALIDATION ---
// Validate that the required database credentials are set (they are loaded as constants from config.php)
if (empty(DB_HOST) || empty(DB_USER) || empty(DB_DATABASE)) {
    die("Error: Database credentials are not fully configured in your .env file.\n");
}

// --- SCRIPT LOGIC ---
// Create connection using the constants defined in config.php
$conn = new mysqli(DB_HOST, DB_USER, DB_PASSWORD, DB_DATABASE, DB_PORT);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Read the main SQL schema file
$sql = file_get_contents(__DIR__ . '/database/create_lottery_table.sql');

if ($sql === false) {
    die("Error: Could not read the SQL file at /database/create_lottery_table.sql\n");
}

// Execute multi query
echo "Attempting to execute database setup script...\n";
if ($conn->multi_query($sql)) {
    // It's important to loop through all results to clear the buffer for the next query
    while ($conn->more_results() && $conn->next_result()) {
        if ($result = $conn->store_result()) {
            $result->free();
        }
    }
    echo "[SUCCESS] Database schema created successfully!\n";
} else {
    echo "[ERROR] Error setting up database: " . $conn->error . "\n";
}

// Now, handle the second SQL file for the bills table
echo "Attempting to set up the 'bills' table...\n";
$sqlBills = file_get_contents(__DIR__ . '/database/create_bills_table.sql');

if ($sqlBills === false) {
    die("Error: Could not read the SQL file at /database/create_bills_table.sql\n");
}

if ($conn->multi_query($sqlBills)) {
    echo "[SUCCESS] 'bills' table created successfully!\n";
} else {
    echo "[ERROR] Error setting up 'bills' table: " . $conn->error . "\n";
}

// Now, handle the users SQL file for the users table
echo "Attempting to set up the 'users' table...\n";
$sqlUsers = file_get_contents(__DIR__ . '/database/create_users_table.sql');

if ($sqlUsers === false) {
    die("Error: Could not read the SQL file at /database/create_users_table.sql\n");
}

if ($conn->multi_query($sqlUsers)) {
    echo "[SUCCESS] 'users' table created successfully!\n";
} else {
    echo "[ERROR] Error setting up 'users' table: " . $conn->error . "\n";
}


$conn->close();
echo "Database setup script finished.\n";
?>