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
