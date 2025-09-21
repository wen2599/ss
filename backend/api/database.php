<?php
// backend/api/database.php

/**
 * A simple, dependency-free class to load .env files.
 */
class DotEnv
{
    protected $path;

    public function __construct(string $path)
    {
        if (!file_exists($path)) {
            throw new \InvalidArgumentException(sprintf('%s does not exist', $path));
        }
        $this->path = $path;
    }

    public function load(): void
    {
        if (!is_readable($this->path)) {
            throw new \RuntimeException(sprintf('%s file is not readable', $this->path));
        }

        $lines = file($this->path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            if (strpos(trim($line), '#') === 0) {
                continue;
            }

            if (strpos($line, '=') === false) {
                continue;
            }

            list($name, $value) = explode('=', $line, 2);
            $name = trim($name);
            $value = trim($value);

            if (!array_key_exists($name, $_SERVER) && !array_key_exists($name, $_ENV)) {
                putenv(sprintf('%s=%s', $name, $value));
                $_ENV[$name] = $value;
                $_SERVER[$name] = $value;
            }
        }
    }
}

/**
 * Function to get a PDO database connection.
 *
 * @return PDO
 * @throws Exception
 */
function getDbConnection(): PDO
{
    // Load environment variables from the .env file in the parent directory
    try {
        $dotenv = new DotEnv(__DIR__ . '/../.env');
        $dotenv->load();
    } catch (\Exception $e) {
        // If .env is missing or unreadable, we rely on server-level env vars.
        // This is fine, but we can log this if we had a logger.
    }

    $host = $_ENV['DB_HOST'] ?? '127.0.0.1';
    $port = $_ENV['DB_PORT'] ?? '3306';
    $db = $_ENV['DB_DATABASE'] ?? null;
    $user = $_ENV['DB_USERNAME'] ?? null;
    $pass = $_ENV['DB_PASSWORD'] ?? null;

    if (!$db || !$user) {
        throw new Exception("Database credentials are not fully configured. Please check your .env file or server environment variables.");
    }

    $dsn = "mysql:host=$host;port=$port;dbname=$db;charset=utf8mb4";
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];

    try {
        return new PDO($dsn, $user, $pass, $options);
    } catch (PDOException $e) {
        // In a real app, log this error to a file, don't just echo it.
        throw new Exception("Database connection failed: " . $e->getMessage());
    }
}
?>
