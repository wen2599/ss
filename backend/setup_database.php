<?php
// A one-time script to set up the database schema.
// From your project root (e.g., public_html), run: php setup_database.php

// --- Bootstrap Application ---
require_once __DIR__ . '/src/config.php';

// --- VALIDATION ---
if (empty(DB_HOST) || empty(DB_USER) || empty(DB_DATABASE)) {
    die("Error: Database credentials are not fully configured in your .env file.\n");
}

// --- SCRIPT LOGIC ---
$conn = new mysqli(DB_HOST, DB_USER, DB_PASSWORD, DB_DATABASE, DB_PORT);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// --- Create Lottery Table ---
echo "Attempting to set up the 'lottery' table...\n";
$sqlLottery = file_get_contents(__DIR__ . '/database/create_lottery_table.sql');
if ($sqlLottery === false) {
    die("Error: Could not read the SQL file at /database/create_lottery_table.sql\n");
}

if ($conn->multi_query($sqlLottery)) {
    // It's important to loop through all results to clear the buffer
    while ($conn->more_results() && $conn->next_result()) { if ($res = $conn->store_result()) { $res->free(); } }
    echo "[SUCCESS] 'lottery' table created successfully!\n";
} else {
    echo "[ERROR] Error setting up 'lottery' table: " . $conn->error . "\n";
}

// --- Create Users Table ---
echo "Attempting to set up the 'users' table...\n";
$sqlUsers = file_get_contents(__DIR__ . '/database/create_users_table.sql');
if ($sqlUsers === false) {
    die("Error: Could not read the SQL file at /database/create_users_table.sql\n");
}

if ($conn->multi_query($sqlUsers)) {
    // Clear buffer for the next potential query
    while ($conn->more_results() && $conn->next_result()) { if ($res = $conn->store_result()) { $res->free(); } }
    echo "[SUCCESS] 'users' table created successfully!\n";
} else {
    echo "[ERROR] Error setting up 'users' table: " . $conn->error . "\n";
}

$conn->close();
echo "Database setup script finished.\n";
?>