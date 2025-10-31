<?php
// backend/db_connection.php
// Version 2.0: Corrected to use DB_PASSWORD and added robust error handling.

if (!function_exists('get_db_connection')) {
    /**
     * Establishes a database connection using credentials from environment variables and returns it.
     *
     * This function now correctly uses 'DB_PASSWORD' as the environment variable for the database password,
     * aligning it with the project's existing conventions. It uses mysqli for the connection.
     *
     * @return mysqli|null Returns a mysqli connection object on success, or null on failure.
     * @throws Exception If the database connection fails, an exception is thrown.
     */
    function get_db_connection() {
        // The env_loader.php script should have already been required and executed.

        $host = getenv('DB_HOST');
        $username = getenv('DB_USER');
        // CORRECTED: Using DB_PASSWORD, not DB_PASS.
        $password = getenv('DB_PASSWORD'); 
        $database = getenv('DB_NAME');

        // A robust check to ensure all required variables were loaded.
        if (empty($host) || empty($username) || empty($database)) {
            error_log('CRITICAL DB ERROR: One or more required database environment variables (DB_HOST, DB_USER, DB_NAME) are missing.');
            // We throw an exception to be caught by the calling script.
            throw new Exception('Server misconfiguration: Database credentials are not fully set.');
        }

        // Suppress the default warning on connection failure to handle it cleanly.
        mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

        try {
            $conn = new mysqli($host, $username, $password, $database);
            
            // Set character set to utf8mb4 for full Unicode support.
            $conn->set_charset("utf8mb4");

            return $conn;

        } catch (mysqli_sql_exception $e) {
            // Log the detailed, real error for server-side debugging.
            error_log("CRITICAL DB CONNECTION FAILED: " . $e->getMessage());
            // Throw a generic, user-safe exception to the calling script.
            throw new Exception('Could not connect to the database.');
        }
    }
}
