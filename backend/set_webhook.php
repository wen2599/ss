<?php
// backend/set_webhook.php

require_once __DIR__ . '/env_loader.php';

$bot_token = $_ENV['TELEGRAM_BOT_TOKEN'];
$webhook_url = 'https://' . $_SERVER['HTTP_HOST'] . '/backend/bot/webhook.php';

$api_url = "https://api.telegram.org/bot{$bot_token}/setWebhook?url={$webhook_url}";

$response = file_get_contents($api_url);

echo $response;
