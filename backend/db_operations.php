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
        $host = $_ENV['DB_HOST'] ?? null;
        $port = $_ENV['DB_PORT'] ?? null;
        $dbname = $_ENV['DB_DATABASE'] ?? null;
        $user = $_ENV['DB_USER'] ?? null;
        $pass = $_ENV['DB_PASSWORD'] ?? null;

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
 * Adds a new email to the authorization list in the database.
 *
 * Called by the Telegram bot when an admin uses the '授权新邮箱' command.
 *
 * @param string $email The email address to authorize.
 * @return bool True on success, false if the email already exists or on database error.
 */
function authorizeEmail($email) {
    $pdo = get_db_connection();
    if (!$pdo) return false;

    try {
        // First, check if the email already exists to prevent duplicates.
        $stmt = $pdo->prepare("SELECT id FROM authorized_emails WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            return false; // Email is already in the database.
        }

        // If not, insert the new email with a default 'pending' status.
        $stmt = $pdo->prepare("INSERT INTO authorized_emails (email, status) VALUES (?, 'pending')");
        return $stmt->execute([$email]);

    } catch (PDOException $e) {
        error_log("Error in authorizeEmail for {$email}: " . $e->getMessage());
        return false;
    }
}

/**
 * Checks if an email is present in the authorized_emails table.
 *
 * Called by the new `check_email.php` endpoint to verify if a user is allowed to register.
 *
 * @param string $email The email to check.
 * @return bool True if the email is found, false otherwise.
 */
function isEmailAuthorized($email) {
    $pdo = get_db_connection();
    if (!$pdo) return false;

    try {
        $stmt = $pdo->prepare("SELECT id FROM authorized_emails WHERE email = ?");
        $stmt->execute([$email]);
        // fetch() returns a row if found, or false if not. We convert this to a boolean.
        return $stmt->fetch() !== false;
    } catch (PDOException $e) {
        error_log("Error in isEmailAuthorized for {$email}: " . $e->getMessage());
        return false;
    }
}