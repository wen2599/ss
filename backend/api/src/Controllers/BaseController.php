<?php
declare(strict_types=1);

namespace App\Controllers;

abstract class BaseController
{
    /**
     * Sends a JSON response.
     *
     * @param int $statusCode The HTTP status code.
     * @param array $data The data to encode as JSON.
     */
    protected function jsonResponse(int $statusCode, array $data): void
    {
        http_response_code($statusCode);
        header('Content-Type: application/json');
        echo json_encode($data);
        exit;
    }

    /**
     * Sends a JSON error response.
     *
     * @param int $statusCode The HTTP status code.
     * @param string $message The error message.
     * @param array $details Additional error details.
     */
    protected function jsonError(int $statusCode, string $message, array $details = []): void
    {
        $payload = ['status' => 'error', 'message' => $message];
        if (!empty($details)) {
            $payload['details'] = $details;
        }
        $this->jsonResponse($statusCode, $payload);
    }

    public function ping(): void
    {
        $this->jsonResponse(200, ['status' => 'success', 'data' => 'Backend is running (Pure PHP)']);
    }
}
