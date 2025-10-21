<?php
declare(strict_types=1);

use DI\Container;
use Slim\Factory\AppFactory;

// 1. 加载 Composer 自动加载器
require __DIR__ . '/vendor/autoload.php';

// 2. [关键修改] 加载位于上级目录的 .env 文件
// __DIR__ 是 /public_html/api，所以 dirname(__DIR__) 是 /public_html
$dotenv = Dotenv\Dotenv::createImmutable(dirname(__DIR__));
$dotenv->load();

// 3. 创建 PHP-DI 容器实例
$container = new Container();

// 4. 将容器实例设置给 AppFactory
AppFactory::setContainer($container);
$app = AppFactory::create();

// 5. 加载依赖项 (数据库、设置等)
$dependencies = require __DIR__ . '/src/dependencies.php';
$dependencies($container);

// 6. 加载中间件 (CORS, 错误处理等)
$middleware = require __DIR__ . '/src/middleware.php';
$middleware($app);

// 7. 加载路由
$routes = require __DIR__ . '/src/routes.php';
$routes($app);

// 8. 运行应用
$app->run();