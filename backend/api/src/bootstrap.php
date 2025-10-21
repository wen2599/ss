<?php
declare(strict_types=1);

use App\Handlers\HttpErrorHandler;
use App\Handlers\ShutdownHandler;
use Slim\App;
use Tuupola\Middleware\CorsMiddleware;

// Function to handle uncaught exceptions
function handleException(Throwable $exception): void {
    $response = [
        'status' => 'error',
        'message' => 'An unexpected error occurred. Please try again later.'
    ];

    // In a development environment, you might want more detail
    if (getenv('DISPLAY_ERROR_DETAILS') === 'true') {
        $response['details'] = [
            'message' => $exception->getMessage(),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'trace' => $exception->getTraceAsString()
        ];
    }

    // Ensure headers are set for JSON response
    if (!headers_sent()) {
        header('Content-Type: application/json');
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

// Set up global error and exception handlers
set_exception_handler('handleException');
set_error_handler('handleError');


return function (App $app) {
    // This function will be called from index.php to configure the app

    // Add CORS Middleware
    $app->add(new CorsMiddleware([
        "origin" => ["*"], // In production, restrict this to your frontend's domain
        "methods" => ["GET", "POST", "PUT", "PATCH", "DELETE", "OPTIONS"],
        "headers.allow" => ["Authorization", "Content-Type", "X-Requested-With"],
        "headers.expose" => [],
        "credentials" => true,
        "cache" => 86400
    ]));

    // Add Body Parsing Middleware (lets Slim handle JSON, form data)
    $app->addBodyParsingMiddleware();

    // Add Routing Middleware
    $app->addRoutingMiddleware();

    // Custom Error Middleware
    $displayErrorDetails = ($_ENV['DISPLAY_ERROR_DETAILS'] ?? 'false') === 'true';
    $callableResolver = $app->getCallableResolver();
    $responseFactory = $app->getResponseFactory();

    $errorHandler = new HttpErrorHandler($callableResolver, $responseFactory);
    $errorMiddleware = $app->addErrorMiddleware($displayErrorDetails, true, true);
    $errorMiddleware->setDefaultErrorHandler($errorHandler);

    // Shutdown handler to catch fatal errors
    register_shutdown_function(new ShutdownHandler());
};
