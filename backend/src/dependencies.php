<?php

declare(strict_types=1);

use Illuminate\Container\Container as IlluminateContainer;
use Illuminate\Database\Capsule\Manager as Capsule;

return function (IlluminateContainer $container) {
    // 创建数据库连接管理器
    $capsule = new Capsule;

    // 添加数据库连接配置
    // !! 请在这里填入你在 Serv00 创建的数据库信息 !!
    $capsule->addConnection([
        'driver'    => 'mysql',
        'host'      => 'localhost',     // 通常是 localhost
        'database'  => 'your_db_name',  // 你的数据库名
        'username'  => 'your_db_user',  // 你的数据库用户名
        'password'  => 'your_db_pass',  // 你的数据库密码
        'charset'   => 'utf8mb4',
        'collation' => 'utf8mb4_unicode_ci',
        'prefix'    => '',
    ]);

    // 设置为全局静态可用 (可选，但方便)
    $capsule->setAsGlobal();

    // 启动 Eloquent ORM
    $capsule->bootEloquent();

    // 将数据库连接实例注册到容器中，方便以后使用
    $container->singleton('db', function () use ($capsule) {
        return $capsule;
    });
};