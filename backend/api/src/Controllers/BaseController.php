<?php
declare(strict_types=1);

namespace App\Controllers;

abstract class BaseController
{
    /**
     * Establishes and returns a PDO database connection.
     * Uses a singleton pattern to ensure only one connection is made per request.
     * @return \PDO The PDO database connection object.
     */
    protected function getDbConnection(): \PDO
    {
        static $conn = null;
        if ($conn === null) {
            // Pre-condition Check: Ensure PDO extension is loaded
            if (!class_exists('PDO')) {
                $this->jsonError(503, 'Service Unavailable: A required server extension (PDO) is missing.');
            }

            $host = $_ENV['DB_HOST'] ?? null;
            $port = (int)($_ENV['DB_PORT'] ?? '3306');
            $dbname = $_ENV['DB_DATABASE'] ?? null;
            $username = $_ENV['DB_USER'] ?? null;
            $password = $_ENV['DB_PASSWORD'] ?? null;

            if (!$host || !$dbname || !$username) {
                $this->jsonError(503, 'Service Unavailable: Server database is not configured correctly.');
            }

            $dsn = "mysql:host={$host};port={$port};dbname={$dbname};charset=utf8mb4";
            $options = [
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
                \PDO::ATTR_EMULATE_PREPARES => false,
            ];
            try {
                $conn = new \PDO($dsn, $username, $password, $options);
            } catch (\PDOException $e) {
                error_log("Database connection failed: " . $e->getMessage());
                $this->jsonError(503, 'Service Unavailable: Could not connect to the database.', $e);
            }
        }
        return $conn;
    }

    /**
     * Sends a JSON response.
     *
     * @param int $statusCode The HTTP status code.
     * @param array $data The data to encode as JSON.
     */
    protected function jsonResponse(int $statusCode, array $data): void
    {
        http_response_code($statusCode);
        if (!headers_sent()) {
            header('Content-Type: application/json');
        }
        echo json_encode($data);
        exit;
    }

    /**
     * Sends a JSON error response by delegating to the global error handler.
     *
     * @param int $statusCode The HTTP status code.
     * @param string $message The error message.
     * @param \Throwable|null $e The exception that caused the error (optional).
     */
    protected function jsonError(int $statusCode, string $message, ?\Throwable $e = null): void
    {
        // Delegate to the global send_json_error function defined in bootstrap.php
        // This ensures consistent error handling including APP_DEBUG details.
        send_json_error($statusCode, $message, $e);
    }

    public function ping(): void
    {
        $this->jsonResponse(200, ['status' => 'success', 'data' => 'Backend is running (Pure PHP)']);
    }

    /**
     * Gets the JSON body from the request.
     *
     * @return array The decoded JSON body.
     * @throws \InvalidArgumentException If the request body is not valid JSON.
     */
    protected function getJsonBody(): array
    {
        $input = file_get_contents('php://input');
        $data = json_decode($input, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \InvalidArgumentException('Invalid JSON body: ' . json_last_error_msg());
        }
        return $data ?? [];
    }
}
