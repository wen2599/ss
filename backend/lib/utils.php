<?php
// backend/lib/utils.php

/**
 * Establishes a database connection and returns the PDO object.
 *
 * @return PDO
 */
function get_db_connection() {
    $host = DB_HOST;
    $db   = DB_NAME;
    $user = DB_USER;
    $pass = DB_PASS;
    $charset = 'utf8mb4';

    $dsn = "mysql:host=$host;dbname=$db;charset=$charset";
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];
    try {
        return new PDO($dsn, $user, $pass, $options);
    } catch (\PDOException $e) {
        // In a real application, you would log this error.
        // For this example, we'll just re-throw it.
        throw new \PDOException($e->getMessage(), (int)$e->getCode());
    }
}

/**
 * Sends a JSON response to the client and terminates the script.
 *
 * @param mixed $data The data to be encoded as JSON.
 * @return void
 */
function send_json_response($data) {
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

?>