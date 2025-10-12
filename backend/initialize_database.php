<?php
// --- Database Initializer CLI Script ---
// This script should be run from the command line: `php initialize_database.php`

// Set the context to command-line interface
if (php_sapi_name() !== 'cli') {
    die("This script can only be run from the command line.");
}

// Bootstrap the application to load environment variables and helpers
require_once __DIR__ . '/config.php';

// --- Configuration ---
// Get credentials using getenv(), which is populated by our config file.
$db_host = getenv('DB_HOST');
$db_user = getenv('DB_USER');
$db_pass = getenv('DB_PASSWORD');
$db_name = getenv('DB_DATABASE');
$sql_file = __DIR__ . '/database_schema.sql';

// --- Pre-flight Checks ---
if (empty($db_host) || empty($db_user) || empty($db_name)) {
    echo "\033[31mError: DB_HOST, DB_USER, and DB_DATABASE must be set in your .env file.\033[0m\n";
    exit(1);
}

if (!file_exists($sql_file)) {
    echo "\033[31mError: SQL schema file not found at {$sql_file}.\033[0m\n";
    exit(1);
}

// --- Main Logic ---
echo "\033[34mStarting database initialization...\033[0m\n";

try {
    // Step 1: Connect to MySQL server (without selecting a DB initially)
    $pdo = new PDO("mysql:host={$db_host}", $db_user, $db_pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);
    echo "\033[32mSuccessfully connected to MySQL server.\033[0m\n";

    // Step 2: Create the database if it doesn't exist.
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$db_name}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;");
    echo "\033[32mDatabase '{$db_name}' created or already exists.\033[0m\n";

    // Step 3: Reconnect to the specific database to run schema.
    $pdo = new PDO("mysql:host={$db_host};dbname={$db_name}", $db_user, $db_pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);

    // Step 4: Read and execute the SQL schema file.
    $sql = file_get_contents($sql_file);
    $pdo->exec($sql);
    echo "\033[32mSuccessfully imported table schema from {$sql_file}.\033[0m\n";

} catch (PDOException $e) {
    echo "\033[31mDatabase Initialization Failed: " . $e->getMessage() . "\033[0m\n";
    exit(1);
}

echo "\033[32mDatabase initialization complete!\033[0m\n";

?>