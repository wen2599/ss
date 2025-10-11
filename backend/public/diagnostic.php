<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Very basic security check - replace 'user_secret_token' with a real one if deploying this long-term
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

echo "<h1>服务器环境与 Webhook 诊断</h1>";
echo "<p>这个脚本会检查您服务器上的 <code>.env</code> 文件配置，并帮助您设置正确的 Telegram Webhook URL。</p>";
echo "<hr><h2>1. 环境配置检查</h2>";
echo "<p>检查 <code>.env</code> 文件中的变量是否已正确加载...</p>";
echo "<ul>";

foreach ($env_vars_to_check as $var) {
    echo "<li><strong>{$var}:</strong> ";
    if (defined($var) && constant($var)) {
        // Do not display sensitive values
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
    echo "<p style='color:red; font-weight:bold;'>&#10071; 错误：一个或多个关键环境变量缺失！请通过 SSH 登录您的服务器，并编辑位于 <code>~/domains/wenge.cloudns.ch/public_html/.env</code> 的文件，确保所有必需的变量都已正确填写。</p>";
    exit;
} else {
    echo "<p style='color:green; font-weight:bold;'>&#10004; 恭喜！所有环境变量都已成功加载。</p>";
}


// --- Webhook Setup ---
$webhook_url = 'https://' . $_SERVER['HTTP_HOST'] . '/backend/public/index.php?endpoint=telegramWebhook';

if (isset($_GET['action']) && $_GET['action'] === 'set_webhook') {
    $set_webhook_api_url = 'https://api.telegram.org/bot' . TELEGRAM_BOT_TOKEN . '/setWebhook?url=' . urlencode($webhook_url) . '&secret_token=' . urlencode(TELEGRAM_WEBHOOK_SECRET);

    $response = @file_get_contents($set_webhook_api_url);
    
    echo "<hr><h2>3. Webhook 设置结果</h2>";
    if ($response === false) {
        echo "<p style='color:red;'><strong>失败！</strong> 无法连接到 Telegram API。请检查您服务器的防火墙或网络出站规则。</p>";
    } else {
        $response_data = json_decode($response, true);
        if ($response_data['ok']) {
            echo "<p style='color:green;'><strong>&#10004; 成功！</strong> Webhook 已被更新为正确的地址:<br><code>" . htmlspecialchars($webhook_url) . "</code></p>";
            echo "<p>Telegram 返回的描述: " . htmlspecialchars($response_data['description']) . "</p>";
            echo "<p><b>现在您的机器人应该可以正常工作了。请去 Telegram 测试 `/admin` 命令！</b></p>";
        } else {
            echo "<p style='color:red;'><strong>&#10008; 失败！</strong> Telegram API 返回了一个错误:<br><code>" . htmlspecialchars($response_data['description']) . "</code></p>";
        }
    }
} else {
    echo "<hr><h2>2. Webhook 设置</h2>";
    echo "<p>根据您的服务器配置，机器人唯一正确的 Webhook URL 应该是:</p>";
    echo "<pre><code>" . htmlspecialchars($webhook_url) . "</code></pre>";
    echo "<p>如果机器人没有响应，很可能是因为 Telegram 还在向旧的、错误的地址发送数据。请点击下面的链接来修正它。</p>";
    echo "<p style='font-size: 1.2em;'><a href='?token=user_secret_token&action=set_webhook'>&#128227; 点击这里，自动设置正确的 Webhook</a></p>";
}

echo '<hr><p style="color:grey;"><i>完成操作后，建议将此诊断文件从服务器上删除以确保安全。</i></p>';

