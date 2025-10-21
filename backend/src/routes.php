<?php

declare(strict_types=1);

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\App;
use Illuminate\Database\Capsule\Manager as DB;

return function (App $app) {
    // 基础的 /ping 路由
    $app->get('/ping', function (Request $request, Response $response) {
        $data = ['status' => 'ok', 'message' => 'pong! API is live.'];
        $response->getBody()->write(json_encode($data));
        return $response->withHeader('Content-Type', 'application/json');
    });

    // 测试数据库连接的路由
    $app->get('/db-test', function (Request $request, Response $response) {
        try {
            DB::connection()->getPdo();
            $data = ['status' => 'ok', 'message' => 'Database connection successful using .env config.'];
        } catch (\Exception $e) {
            $response = $response->withStatus(500);
            $data = ['status' => 'error', 'message' => 'Database connection failed.', 'error' => $e->getMessage()];
        }
        $response->getBody()->write(json_encode($data));
        return $response->withHeader('Content-Type', 'application/json');
    });

    // [新增] 一个用于检查环境变量的路由 (仅供调试，生产环境可考虑移除或加权限)
    $app->get('/env-test', function (Request $request, Response $response) {
        // 出于安全考虑，我们只显示非敏感的环境变量
        $data = [
            'DB_HOST' => $_ENV['DB_HOST'] ?? 'Not Found',
            'BACKEND_PUBLIC_URL' => $_ENV['BACKEND_PUBLIC_URL'] ?? 'Not Found'
        ];
        $response->getBody()->write(json_encode($data));
        return $response->withHeader('Content-Type', 'application/json');
    });
};