<?php
declare(strict_types=1);

namespace App\Handlers;

class ShutdownHandler
{
    public function __invoke()
    {
        $error = error_get_last();
        if ($error && $this->isFatalError($error['type'])) {
            // If we have a fatal error, we send a JSON response.
            $errorMessage = $error['message'];
            $errorFile = $error['file'];
            $errorLine = $error['line'];

            $response = [
                'status' => 'error',
                'message' => 'A fatal error occurred.'
            ];

            if (($_ENV['DISPLAY_ERROR_DETAILS'] ?? 'false') === 'true') {
                $response['details'] = [
                    'message' => $errorMessage,
                    'file' => $errorFile,
                    'line' => $errorLine,
                ];
            }

            if (!headers_sent()) {
                header("Content-Type: application/json");
                http_response_code(500);
            }

            echo json_encode($response);
        }
    }

    private function isFatalError(int $errorType): bool
    {
        return in_array($errorType, [E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR, E_RECOVERABLE_ERROR]);
    }
}
