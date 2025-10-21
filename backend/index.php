<?php
declare(strict_types=1);

use Slim\Factory\AppFactory;
use Illuminate\Container\Container as IlluminateContainer;

require __DIR__ . '/vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

AppFactory::setContainer(new IlluminateContainer());

$app = AppFactory::create();
$app->addRoutingMiddleware();

$middleware = require __DIR__ . '/src/middleware.php';
$middleware($app);

$routes = require __DIR__ . '/src/routes.php';
$routes($app);

$dependencies = require __DIR__ . '/src/dependencies.php';
$dependencies(AppFactory::getContainer());

$errorMiddleware = $app->addErrorMiddleware(true, true, true);

$app->run();