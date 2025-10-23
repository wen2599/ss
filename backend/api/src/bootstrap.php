<?php
declare(strict_types=1);

// Immediately handle CORS preflight requests.
// This ensures that even if the application crashes later, the preflight check succeeds.
if (isset($_SERVER[\'REQUEST_METHOD\']) && $_SERVER[\'REQUEST_METHOD\'] === \'OPTIONS\') {
    header("Access-Control-Allow-Origin: *"); // Be more specific in production
    header("Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS");
    header("Access-Control-Allow-Headers: Authorization, Content-Type, X-Requested-With, X-Worker-Secret");
    header("Access-Control-Allow-Credentials: true");
    header("Access-Control-Max-Age: 86400"); // Cache for 1 day
    http_response_code(204); // No Content
    exit;
}

use App\\Handlers\\HttpErrorHandler;
use App\\Handlers\\ShutdownHandler;
use Slim\\App;

// Function to handle uncaught exceptions
function handleException(Throwable $exception): void {
    $response = [
        \'status\' => \'error\',
        \'message\' => \'An unexpected error occurred. Please try again later.\'
    ];

    // In a development environment, you might want more detail
    if (getenv(\'DISPLAY_ERROR_DETAILS\') === \'true\') {
        $response[\'details\'] = [
            \'message\' => $exception->getMessage(),
            \'file\' => $exception->getFile(),
            \'line\' => $exception->getLine(),
            \'trace\' => $exception->getTraceAsString()
        ];
    }

    // Ensure headers are set for JSON response
    if (!headers_sent()) {
        header(\'Content-Type: application/json\');
        http_response_code(500);
    }

    echo json_encode($response, JSON_PRETTY_PRINT);
    exit;
}

// Function to handle PHP errors (like notices, warnings)
function handleError(int $severity, string $message, string $file, int $line): bool {
    if (!(error_reporting() & $severity)) {
        // This error code is not included in error_reporting
        return false;
    }
    throw new ErrorException($message, 0, $severity, $file, $line);
}

// Set up global error and exception handlers to catch as much as possible
set_exception_handler(\'handleException\');
set_error_handler(\'handleError\');
// Shutdown handler to catch fatal errors that other handlers miss
register_shutdown_function(new ShutdownHandler());


return function (App $app) {
    // This function will be called from index.php to configure the app

    // Add Body Parsing Middleware (lets Slim handle JSON, form data)
    $app->addBodyParsingMiddleware();

    // Add Routing Middleware
    $app->addRoutingMiddleware();

    // Custom Error Middleware for Slim-specific errors
    $displayErrorDetails = ($_ENV[\'DISPLAY_ERROR_DETAILS\'] ?? \'false\') === \'true\';
    $callableResolver = $app->getCallableResolver();
    $responseFactory = $app->getResponseFactory();

    $errorHandler = new HttpErrorHandler($callableResolver, $responseFactory);
    $errorMiddleware = $app->addErrorMiddleware($displayErrorDetails, true, true);
    $errorMiddleware->setDefaultErrorHandler($errorHandler);
};
