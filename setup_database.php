<?php

require_once __DIR__ . '/vendor/autoload.php';

use Monolog\\Logger;
use Monolog\\Handler\\StreamHandler;
use Dotenv\\Dotenv;

// Load .env variables
$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->load();

// Logger Setup
$logLevel = Logger::toMonologLevel($_ENV['LOG_LEVEL'] ?? 'INFO');
$log = new Logger('setup_db');
$log->pushHandler(new StreamHandler(__DIR__ . '/app.log', $logLevel));

// Database credentials from environment variables
$db_host = $_ENV['DB_HOST'] ?? 'localhost';
$db_name = $_ENV['DB_NAME'] ?? 'lottery_app';
$db_user = $_ENV['DB_USER'] ?? 'root';
$db_pass = $_ENV['DB_PASS'] ?? '';

try {
    // 1. Connect to MySQL without specifying a database
    $pdoc = new PDO("mysql:host=$db_host", $db_user, $db_pass);
    $pdoc->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // 2. Create the database if it doesn't exist
    $pdoc->exec("CREATE DATABASE IF NOT EXISTS `$db_name` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    echo "Database '$db_name' created or already exists.\n";
    $log->info("Database '$db_name' created or already exists.");

    // 3. Connect to the newly created database
    $pdo = new PDO("mysql:host=$db_host;dbname=$db_name", $db_user, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // 4. Read the SQL schema from the .sql file
    $sql = file_get_contents(__DIR__ . '/data_table_schema.sql');
    if ($sql === false) {
        throw new Exception("Could not read the data_table_schema.sql file.");
    }

    // 5. Execute the SQL to create the tables
    $pdo->exec($sql);
    echo "Tables created successfully.\n";
    $log->info("Tables created successfully.");

} catch (PDOException $e) {
    $log->error("Database error during setup: " . $e->getMessage());
    echo "Error setting up database. Check logs for details.\n";
    exit(1); // Exit with an error code
} catch (Exception $e) {
    $log->error("General error during setup: " . $e->getMessage());
    echo "Error setting up database. Check logs for details.\n";
    exit(1); // Exit with an error code
}

?>