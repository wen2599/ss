<?php
function route($uri, $method) {
    header('Access-Control-Allow-Origin: https://ss.wenxiuxiu.eu.org');  // 跨域
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization');
    if ($method == 'OPTIONS') exit();  // Preflight

    // 路由映射
    $routes = [
        '/api/auth/register' => 'api/auth.php::register',
        '/api/auth/login' => 'api/auth.php::login',
        '/api/lottery/latest' => 'api/lottery.php::getLatest',
        '/api/lottery/history' => 'api/lottery.php::getHistory',
        '/api/email/insert' => 'api/email.php::insertEmail',  // Workers 调用
        '/api/email/list' => 'api/email.php::getEmails',
        '/api/ai/recognize' => 'api/ai.php::recognize',  // 触发识别
        '/api/ai/dialog' => 'api/ai.php::dialog',  // 对话
        '/api/settlement/calculate' => 'api/settlement.php::calculate',
        '/api/user/delete' => 'api/user.php::deleteUser',  // Bot 调用
        '/bot/webhook' => 'bot/webhook.php::handleWebhook',
    ];

    foreach ($routes as $path => $handler) {
        if (strpos($uri, $path) === 0) {
            list($file, $func) = explode('::', $handler);
            require_once $file;
            call_user_func($func);
            return;
        }
    }
    http_response_code(404);
    echo json_encode(['error' => 'Not Found']);
}