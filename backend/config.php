<?php
// 文件名: config.php
// 路径: backend/config.php
// 用途: 加载.env配置并定义应用程序常量

// 引入我们的.env解析器
require_once __DIR__ . '/core/dotenv.php';

// 加载.env文件
// __DIR__ 是当前文件(config.php)所在的目录 (backend)
// '/../.env' 表示上一级目录的.env文件
$dotenvPath = __DIR__ . '/../.env';
if (file_exists($dotenvPath)) {
    DotEnv::load($dotenvPath);
} else {
    // 如果.env文件不存在，则停止并报错
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'Configuration error: .env file not found.']);
    exit;
}


// --- 定义数据库常量 ---
// 使用 ?? 'default_value' 来提供一个备用值，防止.env中缺少变量时出错
define('DB_HOST', $_ENV['DB_HOST'] ?? 'localhost');
define('DB_PORT', $_ENV['DB_PORT'] ?? '3306');
define('DB_USER', $_ENV['DB_USER'] ?? '');
define('DB_PASS', $_ENV['DB_PASS'] ?? '');
define('DB_NAME', $_ENV['DB_NAME'] ?? '');

// --- 定义Telegram Bot常量 ---
define('TELEGRAM_BOT_TOKEN', $_ENV['TELEGRAM_BOT_TOKEN'] ?? '');
define('TELEGRAM_ADMIN_ID', $_ENV['TELEGRAM_ADMIN_ID'] ?? '');

// --- 定义安全与Webhook常量 ---
define('JWT_SECRET', $_ENV['JWT_SECRET_KEY'] ?? 'default-secret');
define('TELEGRAM_WEBHOOK_SECRET', $_ENV['TELEGRAM_SECRET_TOKEN'] ?? '');
define('EMAIL_WEBHOOK_SECRET', $_ENV['EMAIL_HANDLER_SECRET'] ?? '');

// --- 定义前端域名 (用于CORS) ---
define('FRONTEND_URL', $_ENV['FRONTEND_URL'] ?? 'http://localhost:5173');

// --- 定义AI API密钥常量 ---
define('CLOUDFLARE_ACCOUNT_ID', $_ENV['CLOUDFLARE_ACCOUNT_ID'] ?? '');
define('CLOUDFLARE_API_TOKEN', $_ENV['CLOUDFLARE_API_TOKEN'] ?? '');
define('GEMINI_API_KEY', $_ENV['GEMINI_API_KEY'] ?? '');

// --- 定义Token有效期 ---
define('JWT_EXPIRATION_TIME', 86400 * 7); // 7天