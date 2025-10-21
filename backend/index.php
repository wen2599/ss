<?php

declare(strict_types=1);

use Slim\Factory\AppFactory;
use Illuminate\Container\Container as IlluminateContainer;

// 1. 自动加载 Composer 依赖
require __DIR__ . '/vendor/autoload.php';

// 2. 加载 .env 文件中的环境变量
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

// 3. 使用 illuminate/container 作为依赖注入容器
AppFactory::setContainer(new IlluminateContainer());

// 4. 创建 Slim 应用实例
$app = AppFactory::create();

// 5. 添加路由中间件
$app->addRoutingMiddleware();

// 6. 加载自定义的中间件 (比如 CORS)
$middleware = require __DIR__ . '/src/middleware.php';
$middleware($app);

// 7. 加载自定义的路由
$routes = require __DIR__ . '/src/routes.php';
$routes($app);

// 8. 加载自定义的依赖 (比如数据库连接)
$dependencies = require __DIR__ . '/src/dependencies.php';
$dependencies(AppFactory::getContainer());

// 9. 添加错误处理中间件
$errorMiddleware = $app->addErrorMiddleware(true, true, true);

// 10. 运行应用
$app->run();