<?php

declare(strict_types=1);

use Slim\App;
use Tuupola\Middleware\CorsMiddleware;

return function (App $app) {
    // 添加 CORS 中间件
    $app->add(new CorsMiddleware([
        // 允许的前端域名 (包括本地开发环境和生产环境)
        "origin" => ["https://ss.wenxiuxiu.eu.org", "http://localhost:5173"],
        "methods" => ["GET", "POST", "PUT", "PATCH", "DELETE", "OPTIONS"],
        "headers.allow" => ["Authorization", "Content-Type", "Accept"],
        "credentials" => true, // 如果前端需要发送 cookie
        "cache" => 86400 // 缓存预检请求 (OPTIONS) 结果一天
    ]));
};