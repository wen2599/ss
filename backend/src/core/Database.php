<?php

/**
 * Establishes a connection to the database and returns the connection object.
 *
 * This function reads database credentials (host, user, password, database name)
 * from pre-defined constants and uses them to create a new mysqli connection.
 *
 * It includes basic error handling to terminate the script if the connection fails,
 * logging the error for debugging purposes.
 *
 * @return mysqli The mysqli connection object on success.
 * @throws Exception If the database connection fails.
 */
function getDbConnection(): mysqli
{
    // These constants are expected to be defined in config.php
    $host = DB_HOST;
    $user = DB_USER;
    $pass = DB_PASSWORD;
    $db = DB_DATABASE;
    $port = DB_PORT;

    // Create a new mysqli connection
    $conn = new mysqli($host, $user, $pass, $db, (int)$port);

    // Check for connection errors
    if ($conn->connect_error) {
        // Log the error securely instead of exposing it to the user
        error_log("Database Connection Failed: " . $conn->connect_error);

        // Throw a generic exception to avoid leaking sensitive information
        throw new Exception("Unable to connect to the database.");
    }

    // Set the character set to utf8mb4 for full Unicode support
    if (!$conn->set_charset("utf8mb4")) {
        error_log("Error loading character set utf8mb4: " . $conn->error);
    }

    return $conn;
}