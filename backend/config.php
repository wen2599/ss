<?php
// backend/config.php

// --- 数据库配置 ---
$DB_HOST = 'localhost';
$DB_USER = 'YOUR_DB_USER';  // 请填写实际数据库用户名
$DB_PASS = 'YOUR_DB_PASS';  // 请填写实际数据库密码
$DB_NAME = 'YOUR_DB_NAME';  // 请填写实际数据库名

// --- Telegram Bot 配置 ---
$TELEGRAM_BOT_TOKEN = 'YOUR_BOT_TOKEN'; // 请替换为你的 Telegram Bot Token
$TELEGRAM_BOT_ID = 'YOUR_BOT_ID';       // 通常是bot的username，没有@，如 MyBot 或者 bot的 user id

// --- 其它可扩展配置 ---
// $ADMIN_USER_IDS = [123456789, ...]; // 可选: 管理员TG chat_id数组

// --- END OF FILE backend/config.php ---
?>
