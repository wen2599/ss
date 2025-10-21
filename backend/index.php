<?php

declare(strict_types=1);

use Slim\Factory\AppFactory;
use Illuminate\Container\Container as IlluminateContainer;

// 1. 自动加载 Composer 依赖
// 路径已修正为当前目录下的 vendor
require __DIR__ . '/vendor/autoload.php';

// 2. [可选，推荐] 使用 illuminate/container 作为依赖注入容器
// 这使得在应用各处获取数据库连接等服务变得容易
AppFactory::setContainer(new IlluminateContainer());

// 3. 创建 Slim 应用实例
$app = AppFactory::create();

// 4. (可选，推荐) 添加路由中间件
$app->addRoutingMiddleware();

// 5. 加载自定义的中间件 (比如 CORS)
$middleware = require __DIR__ . '/src/middleware.php';
$middleware($app);

// 6. 加载自定义的路由
$routes = require __DIR__ . '/src/routes.php';
$routes($app);

// 7. 加载自定义的依赖 (比如数据库连接)
$dependencies = require __DIR__ . '/src/dependencies.php';
$dependencies(AppFactory::getContainer());

// 8. (可选但推荐) 添加错误处理中间件
// 应该在最后添加，以便捕获所有之前的错误
$errorMiddleware = $app->addErrorMiddleware(true, true, true);

// 9. 运行应用
$app->run();