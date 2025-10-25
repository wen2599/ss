<?php

require_once __DIR__ . '/vendor/autoload.php';

class EnvLoader {
    private static $dotenv = null;

    public static function load() {
        if (self::$dotenv === null) {
            try {
                // Look for .env in the current directory (__DIR__)
                self::$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
                self::$dotenv->load();
            } catch (\Dotenv\Exception\InvalidPathException $e) {
                error_log("Error loading .env file: " . $e->getMessage());
                // You can choose to die() or handle this gracefully
                die("Critical error: .env file not found.");
            }
        }
    }
}

// Load environment variables
EnvLoader::load();

// --- Database connection ---
$db_host = $_ENV['DB_HOST'];
$db_name = $_ENV['DB_NAME'];
$db_user = $_ENV['DB_USER'];
$db_pass = $_ENV['DB_PASS'];

$db_connection = new mysqli($db_host, $db_user, $db_pass, $db_name);

if ($db_connection->connect_error) {
    error_log("Database connection failed: " . $db_connection->connect_error);
    die("Database connection failed.");
}
