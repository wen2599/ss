<?php
declare(strict_types=1);

use Slim\App;
use Tuupola\Middleware\CorsMiddleware;

return function (App $app) {

    // CORS Middleware
    $app->add(new CorsMiddleware([
        "origin" => ["http://localhost:5173", "https://ss.wenxiuxiu.eu.org"], // Replace with your frontend URLs
        "methods" => ["GET", "POST", "PUT", "PATCH", "DELETE", "OPTIONS"],
        "headers.allow" => ["X-Requested-With", "Content-Type", "Accept", "Origin", "Authorization", "X-Worker-Secret"],
        "headers.expose" => [],
        "credentials" => true,
        "cache" => 86400 // 1 day
    ]));

    // Ensure the 'db' connection is initialized for every request.
    $app->add(function ($request, $handler) use ($app) {
        $container = $app->getContainer();
        $container->get('db'); // This initializes the database connection.
        return $handler->handle($request);
    });
};
