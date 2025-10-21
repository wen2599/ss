<?php
declare(strict_types=1);

use DI\Container;
use Slim\Factory\AppFactory;
use Illuminate\Database\Capsule\Manager as Capsule;

require __DIR__ . '/vendor/autoload.php';

// Load environment variables from the parent directory
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();

// Create Container using PHP-DI
$container = new Container();

// Set up database connection
try {
    $capsule = new Capsule;
    $capsule->addConnection([
        'driver'    => 'mysql',
        'host'      => $_ENV['DB_HOST'],
        'database'  => $_ENV['DB_DATABASE'],
        'username'  => $_ENV['DB_USERNAME'],
        'password'  => $_ENV['DB_PASSWORD'],
        'charset'   => 'utf8',
        'collation' => 'utf8_unicode_ci',
        'prefix'    => '',
    ]);
    $capsule->setAsGlobal();
    $capsule->bootEloquent();
    $container->set('db', $capsule);
} catch (\PDOException $e) {
    // If the database connection fails, we can't do much but log and exit gracefully.
    // The shutdown handler in bootstrap.php should catch this and provide a JSON response.
    // For safety, we'll manually trigger a response here as well.
    if (!headers_sent()) {
        header('Content-Type: application/json');
        http_response_code(503); // Service Unavailable
    }
    echo json_encode([
        'status' => 'error',
        'message' => 'Database connection failed. Please try again later.'
    ]);
    exit;
}


// Set container to create App with
AppFactory::setContainer($container);
$app = AppFactory::create();

// Register dependencies
$dependencies = require __DIR__ . '/src/dependencies.php';
$dependencies($container);


// Bootstrap the application (error handling, middleware, etc.)
$bootstrap = require __DIR__ . '/src/bootstrap.php';
$bootstrap($app);


// Register routes
$routes = require __DIR__ . '/src/routes.php';
$routes($app);

$app->run();
