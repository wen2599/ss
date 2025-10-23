<?php
declare(strict_types=1);

// This file is the single entry point for the entire backend application.

// Use Composer\'s autoloader
require __DIR__ . \'/vendor/autoload.php\';

use DI\\Container;
use Slim\\Factory\\AppFactory;
use Illuminate\\Database\\Capsule\\Manager as Capsule;

// --- Master Error Handling Block ---
// This top-level try-catch block ensures that ANY fatal error during the
// application\'s startup or execution (e.g., failed DB connection, missing
// .env file, fatal PHP error) is caught and handled gracefully.
// This is critical for preventing opaque 500 errors and the resulting
// CORS policy failures on the frontend.
try {
    // --- Environment Loading ---
    // Load environment variables from the project root (three levels up from /api/src)
    try {
        $dotenv = Dotenv\\Dotenv::createImmutable(__DIR__ . \'/../../../\');
        $dotenv->load();
    } catch (\\Dotenv\\Exception\\InvalidPathException $e) {
        // If the .env file is missing, we cannot proceed.
        throw new \\RuntimeException(\"Critical Error: The .env configuration file was not found. Please ensure it exists in the project root.\", 500, $e);\
    }

    // --- Dependency Injection Container ---
    $container = new Container();

    // --- Database Connection (Eloquent) ---
    // The database connection is one of the most common points of failure.
    // It\'s wrapped in its own try-catch to provide a slightly more specific
    // error message if it fails.
    try {
        $capsule = new Capsule;
        $capsule->addConnection([
            \'driver\'    => \'mysql\',
            \'host\'      => $_ENV[\'DB_HOST\'],
            \'database\'  => $_ENV[\'DB_DATABASE\'],
            \'username\'  => $_ENV[\'DB_USERNAME\'],
            \'password\'  => $_ENV[\'DB_PASSWORD\'],
            \'charset\'   => \'utf8\',
            \'collation\' => \'utf8_unicode_ci\',
            \'prefix\'    => \'\',\
        ]);
        $capsule->setAsGlobal();
        $capsule->bootEloquent();
        $container->set(\'db\', $capsule);
    } catch (\\PDOException $e) {
        // If the DB connection fails, throw a new exception that will be
        // caught by the master catch block.
        throw new \\RuntimeException(\"Database connection failed. Check your .env credentials.\", 503, $e);\
    }

    // --- Slim App Initialization ---
    AppFactory::setContainer($container);
    $app = AppFactory::create();

    // --- Application Configuration (Dependencies, Middleware, Routes) ---
    // These files configure the core of the application.
    (require __DIR__ . \'/src/dependencies.php\')($container);\
    (require __DIR__ . \'/src/bootstrap.php\')($app);\
    (require __DIR__ . \'/src/middleware.php\')($app); // <-- LOAD MIDDLEWARE HERE
    (require __DIR__ . \'/src/routes.php\')($app);\

    // --- Run The App ---
    // This is the final step where the application processes the request.
    $app->run();

} catch (\\Throwable $e) {
    // --- Master Catch-All ---
    // This block catches any exception or error from anywhere above.
    // It logs the error and returns a standardized, frontend-friendly JSON response.

    // In a real production environment, you would log to a file or service.
    // For now, we can use PHP\'s built-in error log.
    error_log(\"Uncaught Exception: \" . $e->getMessage() . \" in \" . $e->getFile() . \":\" . $e->getLine() . \"\\n\" . $e->getTraceAsString());

    // Ensure we always return a JSON response, even in a catastrophe.
    if (!headers_sent()) {
        // Determine the appropriate HTTP status code. Use 503 for service
        // unavailable (like DB down), otherwise a generic 500.
        $httpCode = ($e->getCode() === 503) ? 503 : 500;
        header(\'Content-Type: application/json\');
        http_response_code($httpCode);
    }\

    echo json_encode([
        \'status\' => \'error\',
        \'message\' => \'A critical error occurred on the server. Please try again later.\'\
        // For debugging, you might want to uncomment the line below,\
        // but NEVER expose detailed errors in production.\
        // \'details\' => $e->getMessage()\
    ]);
    exit;
}
