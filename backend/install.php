<?php
// backend/install.php

echo "[INFO] Starting database setup...\n";

// Include the environment loader
require_once __DIR__ . '/load_env.php';

// Now, access database credentials from the environment
$db_host = getenv('DB_HOST');
$db_user = getenv('DB_USER');
$db_pass = getenv('DB_PASS');
$db_name = getenv('DB_NAME');

// Check if all required variables are loaded
if (!$db_host || !$db_user || !$db_pass || !$db_name) {
    die("[FATAL] Database configuration is incomplete. Please check your .env file.\n");
}

// Establish a connection to the database server (without selecting a database)
$conn = new mysqli($db_host, $db_user, $db_pass);

// Check connection
if ($conn->connect_error) {
    die("[FATAL] Connection failed: " . $conn->connect_error . "\n");
}

echo "[SUCCESS] Connected to the MySQL server.\n";

// Create the database if it doesn't exist
$sql_create_db = "CREATE DATABASE IF NOT EXISTS `$db_name` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci";
if ($conn->query($sql_create_db) === TRUE) {
    echo "[INFO] Database '$db_name' created or already exists.\n";
} else {
    die("[FATAL] Error creating database: " . $conn->error . "\n");
}

// Select the database
$conn->select_db($db_name);

// Read the SQL setup file
$sql_file = file_get_contents(__DIR__ . '/setup.sql');
if ($sql_file === false) {
    die("[FATAL] Could not read setup.sql file.\n");
}

// Execute the multi-query SQL
if ($conn->multi_query($sql_file)) {
    // Wait for all queries to finish
    while ($conn->next_result()) {;}
    echo "[SUCCESS] Executed SQL script from setup.sql.\n";
    echo "[INFO] Setup complete! The database and tables should be ready.\n";
} else {
    die("[FATAL] Error executing SQL script: " . $conn->error . "\n");
}

// Close the connection
$conn->close();
