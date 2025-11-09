<?php
// File: backend/initialize_database.php
require_once __DIR__ . '/config.php';
echo "--- Database Initialization ---\n";
try {
    $pdo = get_db_connection();
    echo "[SUCCESS] Database connection established.\n";
    $sql = file_get_contents(__DIR__ . '/database_schema.sql');
    $pdo->exec($sql);
    echo "[SUCCESS] All tables created or already exist.\n";
} catch (Exception $e) {
    echo "[FAILURE] " . $e->getMessage() . "\n";
    exit(1);
}
echo "--- Initialization Complete! ---\n";