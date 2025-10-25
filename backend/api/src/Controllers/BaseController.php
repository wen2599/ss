<?php
declare(strict_types=1);

namespace App\Controllers;

abstract class BaseController
{
    /**
     * Retrieves the global PDO database connection.
     * @return \PDO The PDO database connection object.
     */
    protected function getDbConnection(): \PDO
    {
        // Delegates to the global function in bootstrap.php
        return get_db_connection();
    }

    /**
     * Sends a JSON response.
     *
     * @param int $statusCode The HTTP status code.
     * @param array $data The data to encode as JSON.
     */
    protected function jsonResponse(array $data, int $statusCode = 200): void
    {
        if (!headers_sent()) {
            header('Content-Type: application/json');
            http_response_code($statusCode);
        }
        echo json_encode($data);
        exit; // Ensure script termination
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
