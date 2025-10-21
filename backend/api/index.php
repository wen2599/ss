<?php
declare(strict_types=1);

use DI\Container;
use Slim\Factory\AppFactory;

require __DIR__ . '/vendor/autoload.php';

// Load environment variables from the parent directory (public_html)
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();

// Create Container using PHP-DI
$container = new Container();

// Set container to create App with
AppFactory::setContainer($container);
$app = AppFactory::create();

// Register dependencies
$dependencies = require __DIR__ . '/src/dependencies.php';
$dependencies($container);

// Register middleware
$middleware = require __DIR__ . '/src/middleware.php';
$middleware($app);

// Register routes
$routes = require __DIR__ . '/src/routes.php';
$routes($app);

// Add Routing Middleware
$app->addRoutingMiddleware();

// Add Body Parsing Middleware
$app->addBodyParsingMiddleware();

// Add Error Middleware
// This should be the last middleware to be added
$displayErrorDetails = $_ENV['DISPLAY_ERROR_DETAILS'] === 'true';
$errorMiddleware = $app->addErrorMiddleware($displayErrorDetails, true, true);

$app->run();
