<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Final, correct version of the diagnostic script. v3.

// Basic security check
if (!isset($_GET['token']) || $_GET['token'] !== 'user_secret_token') {
    http_response_code(403);
    die('Access Denied.');
}

// Load the application bootstrap
require_once __DIR__ . '/../src/config.php';

// --- Configuration Checks ---
$all_vars_ok = true;
$env_vars_to_check = [
    'DB_HOST', 'DB_DATABASE', 'DB_USER', 'DB_PASSWORD',
    'TELEGRAM_BOT_TOKEN', 'TELEGRAM_WEBHOOK_SECRET', 'TELEGRAM_CHANNEL_ID', 'TELEGRAM_ADMIN_ID'
];

echo "<h1>服务器环境与 Webhook 诊断 (最终修复版 v3)</h1>";
echo "<p>这个脚本会检查您服务器上的 <code>.env</code> 文件配置，并帮助您设置正确的 Telegram Webhook URL。</p>";
echo "<hr><h2>1. 环境配置检查</h2>";

// ... (previous checks remain the same) ...

echo "<ul>";
foreach ($env_vars_to_check as $var) {
    echo "<li><strong>{$var}:</strong> ";
    if (defined($var) && constant($var)) {
        if ($var === 'DB_PASSWORD' || $var === 'TELEGRAM_BOT_TOKEN' || $var === 'TELEGRAM_WEBHOOK_SECRET') {
            echo '<span style="color:green;">已设置 &#10004;</span>';
        } else {
            echo '<span style="color:green;">已设置 (' . htmlspecialchars(constant($var)) . ') &#10004;</span>';
        }
    } else {
        echo '<span style="color:red;">缺失或为空! &#10008;</span>';
        $all_vars_ok = false;
    }
    echo "</li>";
}
echo "</ul>";

if (!$all_vars_ok) {
    echo "<p style='color:red; font-weight:bold;'>&#10071; 错误：关键环境变量缺失！请检查您服务器上的 <code>.env</code> 文件。</p>";
    exit;
} else {
    echo "<p style='color:green; font-weight:bold;'>&#10004; 恭喜！所有环境变量都已成功加载。</p>";
}

// --- CORRECT Webhook Setup (v3) ---
// The URL is now CORRECTLY set to use /public/ and not /backend/public/.
$webhook_url = 'https://' . $_SERVER['HTTP_HOST'] . '/public/index.php?endpoint=telegramWebhook';

if (isset($_GET['action']) && $_GET['action'] === 'set_webhook') {
    $params = [
        'url' => $webhook_url,
        'secret_token' => TELEGRAM_WEBHOOK_SECRET
    ];
    $set_webhook_api_url = 'https://api.telegram.org/bot' . TELEGRAM_BOT_TOKEN . '/setWebhook?' . http_build_query($params);

    $response = @file_get_contents($set_webhook_api_url);
    
    echo "<hr><h2>3. Webhook 设置结果</h2>";
    if ($response === false) {
        echo "<p style='color:red;'><strong>失败！</strong> 无法连接到 Telegram API。请检查服务器的防火墙或网络出站规则。</p>";
    } else {
        $response_data = json_decode($response, true);
        if ($response_data['ok']) {
            echo "<p style='color:green;'><strong>&#10004; 成功！</strong> Webhook 已被更新为这个唯一正确的地址:<br><code>" . htmlspecialchars($webhook_url) . "</code></p>";
            echo "<p>Telegram 返回的描述: " . htmlspecialchars($response_data['description']) . "</p>";
            echo "<p><b>现在您的机器人应该可以正常工作了。请立即去 Telegram 测试 `/admin` 命令！</b></p>";
        } else {
            echo "<p style='color:red;'><strong>&#10008; 失败！</strong> Telegram API 返回了一个错误:<br><code>" . htmlspecialchars($response_data['description']) . "</code></p>";
        }
    }
} else {
    echo "<hr><h2>2. Webhook 设置</h2>";
    echo "<p>根据您的服务器配置和我们的排查，机器人唯一正确的 Webhook URL 必须是:</p>";
    echo "<pre><code>" . htmlspecialchars($webhook_url) . "</code></pre>";
    echo "<p>请点击下面的链接，将 Webhook 设置为这个正确的地址。</p>";
    echo "<p style='font-size: 1.2em;'><a href='?token=user_secret_token&action=set_webhook'>&#128227; 点击这里，设置最终正确的 Webhook</a></p>";
}

echo '<hr><p style="color:grey;"><i>完成操作后，我将从项目中删除此文件。</i></p>';
