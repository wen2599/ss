<?php

function handle_api_request($endpoint) {
    switch ($endpoint) {
        case 'get_numbers.php':
            get_numbers();
            break;
        case 'register.php':
            register_user();
            break;
        case 'login.php':
            login_user();
            break;
        case 'logout.php':
            logout_user();
            break;
        case 'check_session.php':
            check_user_session();
            break;
        case 'telegram_webhook.php':
            handle_telegram_webhook();
            break;
        default:
            http_response_code(404);
            echo json_encode(['error' => 'API endpoint not found.']);
            break;
    }
}

function get_numbers() {
    $data_file = __DIR__ . '/data/numbers.json';
    if (file_exists($data_file)) {
        echo file_get_contents($data_file);
    } else {
        http_response_code(404);
        echo json_encode(['error' => 'Data file not found.']);
    }
}

function register_user() {
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input || !isset($input['username']) || !isset($input['password'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Username and password are required.']);
        return;
    }
    $username = trim($input['username']);
    $password = $input['password'];
    if (empty($username) || empty($password)) {
        http_response_code(400);
        echo json_encode(['error' => 'Username and password cannot be empty.']);
        return;
    }
    $users_file = __DIR__ . '/data/users.json';
    $users = json_decode(file_get_contents($users_file), true);
    foreach ($users as $user) {
        if ($user['username'] === $username) {
            http_response_code(409);
            echo json_encode(['error' => 'Username already exists.']);
            return;
        }
    }
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
    $users[] = ['username' => $username, 'password' => $hashed_password];
    file_put_contents($users_file, json_encode($users, JSON_PRETTY_PRINT));
    echo json_encode(['success' => 'User registered successfully.']);
}

function login_user() {
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input || !isset($input['username']) || !isset($input['password'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Username and password are required.']);
        return;
    }
    $username = $input['username'];
    $password = $input['password'];
    $users_file = __DIR__ . '/data/users.json';
    $users = json_decode(file_get_contents($users_file), true);
    foreach ($users as $user) {
        if ($user['username'] === $username && password_verify($password, $user['password'])) {
            $_SESSION['loggedin'] = true;
            $_SESSION['username'] = $username;
            echo json_encode(['success' => 'Logged in successfully.', 'user' => ['username' => $username]]);
            return;
        }
    }
    http_response_code(401);
    echo json_encode(['error' => 'Invalid username or password.']);
}

function logout_user() {
    session_unset();
    session_destroy();
    echo json_encode(['success' => 'Logged out successfully.']);
}

function check_user_session() {
    if (isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true) {
        echo json_encode(['loggedin' => true, 'user' => ['username' => $_SESSION['username']]]);
    } else {
        echo json_encode(['loggedin' => false]);
    }
}

function handle_telegram_webhook() {
    // The full logic from the original telegram_webhook.php
    $bot_token = $_ENV['TELEGRAM_BOT_TOKEN'] ?? null;
    if (!$bot_token) {
        http_response_code(500);
        error_log("TELEGRAM_BOT_TOKEN is not configured.");
        exit();
    }
    $update = json_decode(file_get_contents('php://input'), true);
    if (!$update || !isset($update['message']['text']) || !isset($update['message']['chat']['id'])) {
        exit();
    }
    $message = $update['message']['text'];
    $chat_id = $update['message']['chat']['id'];
    if (preg_match('/^\/update (\d{8,}) ([\d,\s]+)/', $message, $matches)) {
        $issue = $matches[1];
        $numbers = array_map('intval', explode(',', $matches[2]));
        if (count($numbers) !== 6) {
            sendTelegramReply($chat_id, "Error: Please provide exactly 6 numbers.", $bot_token);
            exit();
        }
        $new_data = ['issue' => $issue, 'numbers' => $numbers];
        $data_file = __DIR__ . '/data/numbers.json';
        if (file_put_contents($data_file, json_encode($new_data, JSON_PRETTY_PRINT))) {
            sendTelegramReply($chat_id, "Success! Numbers for issue {$issue} updated.", $bot_token);
        } else {
            sendTelegramReply($chat_id, "Error: Could not write to data file.", $bot_token);
        }
    } else if (strpos(trim($message), '/update') === 0) {
        sendTelegramReply($chat_id, "Invalid format. Use: /update YYYYMMDD 1,2,3,4,5,6", $bot_token);
    }
}

function sendTelegramReply($chat_id, $message, $bot_token) {
    $api_url = "https://api.telegram.org/bot{$bot_token}/sendMessage";
    $options = ['http' => ['method'  => 'POST', 'header'  => "Content-type: application/x-www-form-urlencoded\r\n", 'content' => http_build_query(['chat_id' => $chat_id, 'text' => $message])]];
    $context = stream_context_create($options);
    file_get_contents($api_url, false, $context);
}

?>