<?php

declare(strict_types=1);

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\App;
use Illuminate\Database\Capsule\Manager as DB; // 引入数据库管理器

return function (App $app) {
    // 基础的 /ping 路由用于测试
    $app->get('/ping', function (Request $request, Response $response) {
        $data = ['status' => 'ok', 'message' => 'pong! API is live.'];
        $response->getBody()->write(json_encode($data));
        return $response->withHeader('Content-Type', 'application/json');
    });

    // 测试数据库连接的路由
    $app->get('/db-test', function (Request $request, Response $response) {
        try {
            // 使用我们配置的数据库连接进行一个简单的查询
            $pdo = DB::connection()->getPdo();
            $data = ['status' => 'ok', 'message' => 'Database connection successful.'];
        } catch (\Exception $e) {
            $response = $response->withStatus(500);
            $data = ['status' => 'error', 'message' => 'Database connection failed.', 'error' => $e->getMessage()];
        }
        $response->getBody()->write(json_encode($data));
        return $response->withHeader('Content-Type', 'application/json');
    });

    // TODO: 未来在这里添加 /emails 相关的路由
    // $app->get('/emails', ...);
    // $app->post('/emails', ...);
    // $app->get('/emails/{id}', ...);
};