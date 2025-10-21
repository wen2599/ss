<?php
declare(strict_types=1);

use DI\Container;
use Illuminate\Database\Capsule\Manager as Capsule;
use Psr\Container\ContainerInterface;

return function (Container $container) {
    // 注册设置
    $container->set('settings', function() {
        return [
            'displayErrorDetails' => ($_ENV['DISPLAY_ERROR_DETAILS'] ?? 'false') === 'true',
            'db' => [
                'driver'    => 'mysql',
                'host'      => $_ENV['DB_HOST'],
                'database'  => $_ENV['DB_DATABASE'],
                'username'  => $_ENV['DB_USER'],
                'password'  => $_ENV['DB_PASSWORD'],
                'charset'   => 'utf8mb4',
                'collation' => 'utf8mb4_unicode_ci',
                'prefix'    => '',
            ]
        ];
    });

    // 注册数据库连接 (Eloquent)
    $container->set('db', function (ContainerInterface $c) {
        $capsule = new Capsule;
        $capsule->addConnection($c->get('settings')['db']);
        $capsule->setAsGlobal();
        $capsule->bootEloquent();
        return $capsule;
    });
};