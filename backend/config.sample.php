<?php
// 文件名: config.sample.php
// 路径: backend/config.sample.php
// 用途: 配置示例文件, 请复制为 config.php 并填入你的信息

// --- 数据库配置 (Serv00) ---
define('DB_HOST', 'localhost');
define('DB_USER', 'your_serv00_db_user');
define('DB_PASS', 'your_serv00_db_password');
define('DB_NAME', 'your_serv00_db_name');

// --- Telegram Bot 配置 ---
define('TELEGRAM_BOT_TOKEN', 'YOUR_TELEGRAM_BOT_TOKEN');
// 你的Telegram User ID, 用于管理员验证。你可以发消息给 @userinfobot 获取
define('TELEGRAM_ADMIN_ID', 'YOUR_TELEGRAM_USER_ID'); 

// --- Webhook 安全密钥 ---
// Cloudflare Worker调用Email Webhook时使用的密钥
define('EMAIL_WEBHOOK_SECRET', 'a_very_long_and_random_secret_string_for_email');
// 设置Telegram Webhook时使用的密钥, 增加安全性
define('TELEGRAM_WEBHOOK_SECRET', 'a_very_long_and_random_secret_string_for_telegram');

// --- JWT (JSON Web Token) 配置 ---
define('JWT_SECRET', 'another_very_long_and_random_secret_for_jwt');
define('JWT_EXPIRATION_TIME', 86400 * 7); // Token有效期 (7天)

// --- 前端域名 (用于CORS) ---
define('FRONTEND_URL', 'https://ss.wenxiuxiu.eu.org');
?>