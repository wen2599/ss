<?php
// backend/db_connection.php
// Version 2.1: Prioritize $_ENV and $_SERVER for robust environment variable loading.

if (!function_exists('get_db_connection')) {
    /**
     * Establishes a database connection using credentials from environment variables and returns it.
     *
     * This function now prioritizes $_ENV and $_SERVER superglobals, falling back to getenv()
     * for database credentials. This enhances compatibility across different server setups.
     *
     * @return mysqli|null Returns a mysqli connection object on success, or null on failure.
     * @throws Exception If the database connection fails, an exception is thrown.
     */
    function get_db_connection() {
        // The env_loader.php script should have already been required and executed,
        // populating $_ENV and $_SERVER.

        // Prioritize $_ENV, then $_SERVER, then getenv() for maximum compatibility.
        $host = $_ENV['DB_HOST'] ?? $_SERVER['DB_HOST'] ?? getenv('DB_HOST');
        $username = $_ENV['DB_USER'] ?? $_SERVER['DB_USER'] ?? getenv('DB_USER');
        $password = $_ENV['DB_PASSWORD'] ?? $_SERVER['DB_PASSWORD'] ?? getenv('DB_PASSWORD'); 
        $database = $_ENV['DB_NAME'] ?? $_SERVER['DB_NAME'] ?? getenv('DB_NAME');

        // A robust check to ensure all required variables were loaded.
        if (empty($host) || empty($username) || empty($database)) {
            error_log('CRITICAL DB ERROR: One or more required database environment variables (DB_HOST, DB_USER, DB_NAME) are missing. Check .env file and server setup.');
            throw new Exception('Server misconfiguration: Database credentials are not fully set.');
        }

        mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

        try {
            $conn = new mysqli($host, $username, $password, $database);
            
            $conn->set_charset("utf8mb4");

            return $conn;

        } catch (mysqli_sql_exception $e) {
            error_log("CRITICAL DB CONNECTION FAILED: " . $e->getMessage() . ". Host: {$host}, User: {$username}, DB: {$database}");
            throw new Exception('Could not connect to the database.');
        }
    }
}
