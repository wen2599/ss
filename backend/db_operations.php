<?php

/**
 * Establishes and returns a singleton PDO database connection.
 *
 * This function reads database credentials from environment variables and uses a static
 * variable to ensure that only one database connection is made per request lifecycle.
 * This is crucial for performance and resource management.
 *
 * @return PDO|null A configured PDO object on success, or null if connection fails.
 */
function get_db_connection() {
    static $pdo = null;

    if ($pdo === null) {
        // Load credentials from environment variables.
        $host = getenv('DB_HOST');
        $port = getenv('DB_PORT');
        $dbname = getenv('DB_DATABASE');
        $user = getenv('DB_USER');
        $pass = getenv('DB_PASSWORD');

        // All credentials are required.
        if (empty($host) || empty($port) || empty($dbname) || empty($user)) {
             error_log("Database connection error: Required environment variables are not set.");
             return null;
        }

        $dsn = "mysql:host={$host};port={$port};dbname={$dbname};charset=utf8mb4";
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION, // Throw exceptions on error.
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,   // Fetch as associative arrays.
            PDO::ATTR_EMULATE_PREPARES   => false,              // Use native prepared statements.
        ];

        try {
            $pdo = new PDO($dsn, $user, $pass, $options);
        } catch (PDOException $e) {
            // Log error securely, don't expose to the user.
            error_log("Database connection failed: " . $e->getMessage());
            return null;
        }
    }
    
    return $pdo;
}

/**
 * Retrieves all users from the database.
 *
 * @return array An array of user objects (or an empty array if none found).
 */
function getAllUsers() {
    $pdo = get_db_connection();
    if (!$pdo) return [];

    try {
        $stmt = $pdo->query("SELECT id, email, created_at FROM users ORDER BY created_at DESC");
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log("Error in getAllUsers: " . $e->getMessage());
        return [];
    }
}

/**
 * Deletes a user from the database by their email address.
 *
 * @param string $email The email of the user to delete.
 * @return bool True on successful deletion, false otherwise.
 */
function deleteUserByEmail($email) {
    $pdo = get_db_connection();
    if (!$pdo) return false;

    try {
        $stmt = $pdo->prepare("DELETE FROM users WHERE email = ?");
        $stmt->execute([$email]);
        // rowCount() returns the number of affected rows.
        // If it's greater than 0, the deletion was successful.
        return $stmt->rowCount() > 0;
    } catch (PDOException $e) {
        error_log("Error in deleteUserByEmail for {$email}: " . $e->getMessage());
        return false;
    }
}