<?php
// 文件名: config.php
// 路径: 项目根目录

require_once __DIR__ . '/core/dotenv.php';

// __DIR__ 是项目根目录, .env 在上一级
$dotenvPath = __DIR__ . '/../.env'; 

if (file_exists($dotenvPath)) {
    DotEnv::load($dotenvPath);
} else {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'Configuration error: .env file not found.']);
    exit;
}

// --- 定义数据库常量 ---
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