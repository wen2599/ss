<?php
declare(strict_types=1);

use Psr\Container\ContainerInterface;
use Slim\App;
use Tuupola\Middleware\CorsMiddleware;

return function (App $app) {
    $container = $app->getContainer();

    // 添加 CORS 中-中间件
    $app->add(new CorsMiddleware([
        "origin" => ["https://ss.wenxiuxiu.eu.org", "http://localhost:5173"],
        "methods" => ["GET", "POST", "PUT", "PATCH", "DELETE", "OPTIONS"],
        "headers.allow" => ["Authorization", "Content-Type", "Accept"],
        "credentials" => true,
        "cache" => 86400
    ]));

    // 添加路由中间件
    $app->addRoutingMiddleware();

    // 添加错误处理中间件 (必须在最后添加)
    $settings = $container->get('settings');
    $errorMiddleware = $app->addErrorMiddleware(
        $settings['displayErrorDetails'],
        true,
        true
    );
};