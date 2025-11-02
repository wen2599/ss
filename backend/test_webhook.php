<?php
// backend/test_webhook.php
// 用于直接从命令行测试 telegram_webhook.php

// 确保此脚本只能从命令行运行
if (php_sapi_name() !== 'cli') {
    die("This script can only be run from the command line.\n");
}

// 使用我们自己的加载器加载 .env 文件
require_once __DIR__ . '/utils/config_loader.php';
$dotenv_path = __DIR__ . '/.env'; // .env 文件在项目根目录
load_env($dotenv_path);


// 模拟 $_SERVER 变量，特别是 SECRET_TOKEN
$_SERVER['HTTP_X_TELEGRAM_BOT_API_SECRET_TOKEN'] = $_ENV['TELEGRAM_WEBHOOK_SECRET'] ?? 'dummy_secret';
$_SERVER['REQUEST_METHOD'] = 'POST';

// 获取管理员ID以模拟正确的发送者
$admin_id = $_ENV['TELEGRAM_ADMIN_ID'] ?? '123456789'; // 使用一个默认值，如果未设置

// 模拟一个 /start 命令的 Telegram Webhook 更新对象
$mock_update = [
    'update_id' => 100000001,
    'message' => [
        'message_id' => 1,
        'from' => [
            'id' => (int)$admin_id, // 确保是整数
            'is_bot' => false,
            'first_name' => 'Test',
            'last_name' => 'Admin',
            'language_code' => 'zh-hans'
        ],
        'chat' => [
            'id' => (int)$admin_id, // 确保是整数
            'first_name' => 'Test',
            'last_name' => 'Admin',
            'type' => 'private'
        ],
        'date' => time(),
        'text' => '/start'
    ]
];

// 准备将模拟数据传递给 webhook 脚本
$GLOBALS['__MOCKED_TELEGRAM_UPDATE__'] = $mock_update;

echo "--- Starting simulated Telegram Webhook for /start command ---\n";

// 直接包含 webhook 脚本。这样所有错误都会直接输出到终端。
require_once __DIR__ . '/telegram_webhook.php';

echo "--- Simulated Webhook Finished ---\n";
