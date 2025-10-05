<?php
// ==================================================================
// == Single-File Backend for Lottery Application                  ==
// ==================================================================
// This file contains the entire backend logic, merged to ensure
// maximum compatibility with various server environments by
// eliminating file inclusion issues.

// --- Step 1: Force Error Logging for Debugging ---
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/error.log');

// --- Step 2: Simple Request Logging for Debugging ---
$log_message = date('[Y-m-d H:i:s]') . " " . $_SERVER['REQUEST_METHOD'] . " " . $_SERVER['REQUEST_URI'] . "\n";
file_put_contents(__DIR__ . '/request.log', $log_message, FILE_APPEND);

// --- Step 3: Bootstrap the Application (.env loading) ---
function load_env($path) {
    if (!is_readable($path)) { return; }
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines === false) { return; }
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) { continue; }
        list($name, $value) = explode('=', $line, 2);
        $name = trim($name);
        $value = trim($value);
        if (!array_key_exists($name, $_SERVER) && !array_key_exists($name, $_ENV)) {
            putenv(sprintf('%s=%s', $name, $value));
            $_ENV[$name] = $value;
            $_SERVER[$name] = $value;
        }
    }
}
load_env(__DIR__ . '/.env');

// --- Step 4: Configuration ---
define('WORKER_SECRET', getenv('WORKER_SECRET') ?: '816429fb-1649-4e48-9288-7629893311a6');
define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
define('DB_USER', getenv('DB_USER') ?: 'your_db_user');
define('DB_PASS', getenv('DB_PASS') ?: 'your_db_password');
define('DB_NAME', getenv('DB_NAME') ?: 'your_db_name');
define('TELEGRAM_BOT_TOKEN', getenv('TELEGRAM_BOT_TOKEN'));
define('TELEGRAM_ADMIN_ID', getenv('TELEGRAM_ADMIN_ID'));
define('UPLOADS_DIR', __DIR__ . '/uploads');


// --- Step 5: Helper Functions ---
function send_json_response($data, $status_code = 200) {
    http_response_code($status_code);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

function get_db_connection() {
    $conn = @new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    if ($conn->connect_error) {
        error_log("Database connection failed: " . $conn->connect_error);
        return null;
    }
    return $conn;
}

function send_telegram_message($chat_id, $message) {
    $bot_token = TELEGRAM_BOT_TOKEN;
    if (empty($bot_token) || empty($chat_id)) { return false; }
    $api_url = "https://api.telegram.org/bot{$bot_token}/sendMessage";
    $payload = ['chat_id' => $chat_id, 'text' => $message, 'parse_mode' => 'Markdown'];
    $options = ['http' => ['method'  => 'POST', 'header'  => "Content-type: application/json\r\n", 'content' => json_encode($payload), 'ignore_errors' => true]];
    $context = stream_context_create($options);
    $result = file_get_contents($api_url, false, $context);
    if ($result === false) {
        error_log("Telegram API request failed completely.");
        return false;
    }
    $response_data = json_decode($result, true);
    if (!$response_data['ok']) {
        error_log("Telegram API Error: " . ($response_data['description'] ?? 'Unknown error'));
        return false;
    }
    return true;
}

// --- Step 6: CORS and Preflight Request Handling ---
$allowed_origins = ['http://localhost:5173', 'https://ss.wenxiuxiu.eu.org'];
if (isset($_SERVER['HTTP_ORIGIN']) && in_array($_SERVER['HTTP_ORIGIN'], $allowed_origins)) {
    header("Access-Control-Allow-Origin: {$_SERVER['HTTP_ORIGIN']}");
    header('Access-Control-Allow-Credentials: true');
    header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
    header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
}
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    if (isset($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_METHOD'])) { header("HTTP/1.1 200 OK"); }
    exit(0);
}

// --- Step 7: Main Router ---
$endpoint = $_GET['endpoint'] ?? 'not_found';
$endpoint = basename($endpoint, '.php');
$endpoint = preg_replace('/[^a-zA-Z0-9_]/', '', $endpoint);

if (empty($endpoint)) {
    $endpoint = 'not_found';
}

switch ($endpoint) {
    case 'get_numbers':
        // Logic from get_numbers.php
        if ($_SERVER['REQUEST_METHOD'] !== 'GET') { send_json_response(['error' => 'Method not allowed.'], 405); }
        $conn = get_db_connection();
        if (!$conn) { send_json_response(['error' => 'Database connection failed.'], 500); }
        $result = $conn->query("SELECT issue, numbers FROM lottery_numbers ORDER BY issue DESC LIMIT 1");
        if (!$result) { error_log("DB query failed: " . $conn->error); send_json_response(['error' => 'Could not fetch lottery data.'], 500); }
        $data = $result->fetch_assoc();
        if (!$data) { send_json_response(['error' => 'No lottery data found.'], 404); }
        $data['numbers'] = array_map('intval', explode(',', $data['numbers']));
        $conn->close();
        send_json_response($data);
        break;

    case 'check_session':
        // Logic from check_session.php
        session_start();
        if (isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true) {
            send_json_response(['loggedin' => true, 'user' => ['username' => $_SESSION['username'] ?? 'Unknown']]);
        } else {
            send_json_response(['loggedin' => false]);
        }
        break;

    case 'login':
        // Logic from login.php
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') { send_json_response(['error' => 'Method not allowed.'], 405); }
        $input = json_decode(file_get_contents('php://input'), true);
        if (!$input || !isset($input['username']) || !isset($input['password'])) { send_json_response(['error' => 'Username and password are required.'], 400); }
        $username = $input['username'];
        $password = $input['password'];
        $conn = get_db_connection();
        if (!$conn) { send_json_response(['error' => 'Database connection failed.'], 500); }
        $stmt = $conn->prepare("SELECT username, password FROM users WHERE username = ?");
        if (!$stmt) { error_log("DB prepare statement failed: " . $conn->error); send_json_response(['error' => 'Database query failed.'], 500); }
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows === 0) { send_json_response(['error' => 'Invalid username or password.'], 401); }
        $user = $result->fetch_assoc();
        if (password_verify($password, $user['password'])) {
            session_start();
            $_SESSION['loggedin'] = true;
            $_SESSION['username'] = $user['username'];
            send_json_response(['success' => true, 'user' => ['username' => $user['username']]]);
        } else {
            send_json_response(['error' => 'Invalid username or password.'], 401);
        }
        $stmt->close();
        $conn->close();
        break;

    case 'logout':
        // Logic from logout.php
        session_start();
        $_SESSION = [];
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params["path"], $params["domain"], $params["secure"], $params["httponly"]);
        }
        session_destroy();
        send_json_response(['success' => true, 'message' => 'Logged out successfully.']);
        break;

    case 'register':
        // Logic from register.php
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') { send_json_response(['error' => 'Method not allowed.'], 405); }
        $input = json_decode(file_get_contents('php://input'), true);
        if (!$input || !isset($input['username']) || !isset($input['password'])) { send_json_response(['error' => 'Username and password are required.'], 400); }
        $email = trim($input['username']);
        $password = $input['password'];
        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) { send_json_response(['error' => 'A valid email address must be used as the username.'], 400); }
        if (empty($password)) { send_json_response(['error' => 'Password cannot be empty.'], 400); }
        $conn = get_db_connection();
        if (!$conn) { send_json_response(['error' => 'Database connection failed.'], 500); }

        $stmt_allowed = $conn->prepare("SELECT id FROM allowed_emails WHERE email = ?");
        if (!$stmt_allowed) { error_log("DB prepare statement failed (check allowed): " . $conn->error); send_json_response(['error' => 'Database query failed.'], 500); }
        $stmt_allowed->bind_param("s", $email);
        $stmt_allowed->execute();
        $stmt_allowed->store_result();
        if ($stmt_allowed->num_rows === 0) { $stmt_allowed->close(); $conn->close(); send_json_response(['error' => '此邮箱未被授权注册，请联系管理员。'], 403); }
        $stmt_allowed->close();

        $stmt_check = $conn->prepare("SELECT username FROM users WHERE username = ?");
        if (!$stmt_check) { error_log("DB prepare statement failed (check users): " . $conn->error); send_json_response(['error' => 'Database query failed.'], 500); }
        $stmt_check->bind_param("s", $email);
        $stmt_check->execute();
        $stmt_check->store_result();
        if ($stmt_check->num_rows > 0) { $stmt_check->close(); $conn->close(); send_json_response(['error' => 'Username already exists.'], 409); }
        $stmt_check->close();

        $conn->begin_transaction();
        try {
            $stmt_insert = $conn->prepare("INSERT INTO users (username, password, email) VALUES (?, ?, ?)");
            if (!$stmt_insert) throw new Exception("DB prepare statement failed (insert user): " . $conn->error);
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $stmt_insert->bind_param("sss", $email, $hashed_password, $email);
            if (!$stmt_insert->execute()) throw new Exception("DB execute failed (insert user): " . $stmt_insert->error);
            $stmt_insert->close();

            $stmt_delete = $conn->prepare("DELETE FROM allowed_emails WHERE email = ?");
            if (!$stmt_delete) throw new Exception("DB prepare statement failed (delete allowed): " . $conn->error);
            $stmt_delete->bind_param("s", $email);
            if (!$stmt_delete->execute()) throw new Exception("DB execute failed (delete allowed): " . $stmt_delete->error);
            $stmt_delete->close();

            $conn->commit();
            send_json_response(['success' => true, 'message' => 'User registered successfully.'], 201);
        } catch (Exception $e) {
            $conn->rollback();
            error_log($e->getMessage());
            send_json_response(['error' => 'Failed to register user due to a server error.'], 500);
        }
        $conn->close();
        break;

    case 'tg_webhook':
        // Logic from tg_webhook.php
        if (!defined('TELEGRAM_BOT_TOKEN') || !defined('TELEGRAM_ADMIN_ID')) { error_log("Telegram bot token or admin ID is not configured."); exit; }
        $update = json_decode(file_get_contents('php://input'), true);
        if (!$update || !isset($update['message']['text']) || !isset($update['message']['from']['id'])) { exit; }
        $message = $update['message'];
        $chat_id = $message['chat']['id'];
        $user_id = $message['from']['id'];
        $text = trim($message['text']);
        if ((string)$user_id !== (string)TELEGRAM_ADMIN_ID) { exit; }

        if (strpos($text, '/add_email') === 0) {
            $parts = explode(' ', $text, 2);
            $email_to_add = trim($parts[1] ?? '');
            if (empty($email_to_add) || !filter_var($email_to_add, FILTER_VALIDATE_EMAIL)) {
                send_telegram_message($chat_id, "❌ Invalid format. Please use: `/add_email user@example.com`");
                exit;
            }
            $conn = get_db_connection();
            if (!$conn) { send_telegram_message($chat_id, "🚨 *Error:* Could not connect to the database. Please check the server logs."); exit; }

            $stmt_check = $conn->prepare("SELECT email FROM allowed_emails WHERE email = ?");
            $stmt_check->bind_param("s", $email_to_add);
            $stmt_check->execute();
            $stmt_check->store_result();
            if ($stmt_check->num_rows > 0) {
                send_telegram_message($chat_id, "⚠️ This email `{$email_to_add}` is already on the allowed list.");
                $stmt_check->close(); $conn->close(); exit;
            }
            $stmt_check->close();

            $stmt_insert = $conn->prepare("INSERT INTO allowed_emails (email) VALUES (?)");
            $stmt_insert->bind_param("s", $email_to_add);
            if ($stmt_insert->execute()) {
                send_telegram_message($chat_id, "✅ *Success!* The email `{$email_to_add}` has been added to the allowed list.");
            } else {
                error_log("Failed to insert allowed email: " . $stmt_insert->error);
                send_telegram_message($chat_id, "🚨 *Error:* Could not add the email to the database.");
            }
            $stmt_insert->close();
            $conn->close();
        } else {
            $help_text = "🤖 Hello Admin! I'm alive.\n\nTo add a user, use the command:\n`/add_email user@example.com`";
            send_telegram_message($chat_id, $help_text);
        }
        break;

    default:
        send_json_response(['error' => "API endpoint '{$endpoint}' not found."], 404);
        break;
}
?>