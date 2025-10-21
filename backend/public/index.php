<?php

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Factory\AppFactory;
use Tuupola\Middleware\CorsMiddleware;

// 1. 自动加载依赖
require __DIR__ . '/../vendor/autoload.php';

// 2. 创建 Slim 应用实例
$app = AppFactory::create();

// 3. (可选但推荐) 添加路由中间件，它允许应用匹配路由
$app->addRoutingMiddleware();

// 4. 添加 CORS 中间件 - 这是解决跨域问题的核心！
$app->add(new CorsMiddleware([
    "origin" => ["https://ss.wenxiuxiu.eu.org", "http://localhost:5173"],
    "methods" => ["GET", "POST", "PUT", "PATCH", "DELETE", "OPTIONS"],
    "headers.allow" => ["Authorization", "Content-Type"],
    "credentials" => true,
    "cache" => 86400 // 缓存预检请求结果一天
]));

// 5. (可选但推荐) 添加错误处理中间件
$errorMiddleware = $app->addErrorMiddleware(true, true, true);

// =========================================================
//  API 路由定义区
// =========================================================

// 定义一个 GET /ping 路由用于测试
$app->get('/ping', function (Request $request, Response $response) {
    $data = [
        'status' => 'ok',
        'message' => 'pong from the backend!',
        'timestamp' => time()
    ];
    $payload = json_encode($data);

    $response->getBody()->write($payload);
    return $response->withHeader('Content-Type', 'application/json');
});

// =========================================================

// 6. 运行应用
$app->run();