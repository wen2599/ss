<?php
// backend/db_connection.php
// Version 1.0: Establishes and returns a database connection.

if (!function_exists('get_db_connection')) {
    /**
     * Creates and returns a mysqli database connection object.
     *
     * This function relies on the environment variables loaded by 'env_loader.php'.
     * It will throw an exception if the connection to the database fails,
     * which will be caught by the calling script's error handler.
     *
     * @return mysqli A mysqli connection object.
     * @throws Exception If the database connection fails.
     */
    function get_db_connection() {
        // Retrieve database credentials securely from environment variables.
        $host = getenv('DB_HOST');
        $user = getenv('DB_USER');
        $pass = getenv('DB_PASS');
        $dbname = getenv('DB_NAME');

        // Check if all required environment variables are loaded.
        if (!$host || !$user || !$pass || !$dbname) {
            // This will trigger a fatal error, which is intended behavior
            // as the application cannot function without a database.
            // Our production error handler in receive_email.php will catch this.
            throw new Exception("Database credentials are not fully configured in the .env file.");
        }

        // Suppress warnings and use exceptions for error handling.
        mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

        try {
            $conn = new mysqli($host, $user, $pass, $dbname);
            
            // Set the character set to utf8mb4 for full Unicode support.
            $conn->set_charset("utf8mb4");

            return $conn;
        } catch (mysqli_sql_exception $e) {
            // Re-throw the exception to be handled by the global error handler.
            // This prevents leaking sensitive connection details.
            throw new Exception("Database connection failed: " . $e->getMessage(), $e->getCode());
        }
    }
}
?>