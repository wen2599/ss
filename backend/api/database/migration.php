<?php

// Load environment variables (consider using a library like phpdotenv in a real project)
// For this example, we'll assume environment variables are set or use defaults.
// In a production environment, NEVER hardcode credentials or expose .env files.

function getDbConnection(): PDO {
    static $conn = null;

    if ($conn === null) {
        // Attempt to get credentials from environment variables
        $host = getenv('DB_HOST') ?: '127.0.0.1';
        $dbname = getenv('DB_DATABASE') ?: 'email_viewer';
        $username = getenv('DB_USERNAME') ?: 'root';
        $password = getenv('DB_PASSWORD') ?: '';

        // Basic validation for essential credentials
        if (empty($dbname) || empty($username)) {
            error_log('Database credentials (DB_DATABASE or DB_USERNAME) are not set.');
            // In a real application, you might throw an exception or handle this more gracefully.
            // For now, we'll die to prevent further execution with misconfigured DB.
            die("Server configuration error: Database credentials missing.");
        }

        $dsn = "mysql:host=$host;dbname=$dbname;charset=utf8mb4";
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION, // Throw exceptions on errors
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,     // Fetch results as associative arrays
            PDO::ATTR_EMULATE_PREPARES   => false,                 // Disable emulation for better security and performance
        ];

        try {
            $conn = new PDO($dsn, $username, $password, $options);
        } catch (PDOException $e) {
            error_log("Database connection failed: " . $e->getMessage());
            // In a real application, consider a more user-friendly error page or message.
            die("Database connection unavailable: " . $e->getMessage());
        }
    }
    return $conn;
}

// Function to run database migrations
function runMigrations(PDO $pdo):
{
    $sql = file_get_contents(__DIR__ . '/migration.sql');
    if ($sql === false) {
        error_log("Failed to read migration.sql file.");
        return;
    }
    try {
        $pdo->exec($sql);
        // Optionally log success
        // error_log("Database migrations executed successfully.");
    } catch (PDOException $e) {
        error_log("Database migration failed: " . $e->getMessage());
    }
}

?>