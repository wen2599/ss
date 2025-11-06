<?php
// api.php

ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

// --- Global Headers ---
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

// --- Handle pre-flight OPTIONS requests ---
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// --- Helper function for sending JSON responses ---
function json_response($data, $status_code = 200) {
    http_response_code($status_code);
    echo json_encode($data);
    exit();
}

// --- Security function to verify worker secret ---
function verify_worker_secret() {
    $worker_secret = isset($_REQUEST['worker_secret']) ? $_REQUEST['worker_secret'] : '';
    $expected_secret = get_env_variable('EMAIL_HANDLER_SECRET');

    if (empty($expected_secret) || $worker_secret !== $expected_secret) {
        error_log("API Security: Worker secret mismatch or not configured.");
        json_response(['success' => false, 'message' => 'Forbidden.'], 403);
    }
}


// --- Main API Logic ---
try {
    // Load configuration and database inside the try block.
    // This ensures that any failure during initialization is caught
    // and returned as a proper JSON error.
    require_once 'config.php';
    require_once 'database.php';

    $action = isset($_REQUEST['action']) ? $_REQUEST['action'] : '';

    switch ($action) {

        case 'get_latest_lottery_result':
            $latest_result = Database::getLatestLotteryResult();
            if ($latest_result) {
                json_response(['success' => true, 'data' => $latest_result]);
            } else {
                json_response(['success' => false, 'message' => 'No lottery results found.']);
            }
            break;

        case 'is_user_registered':
            verify_worker_secret();
            $email = isset($_GET['email']) ? trim($_GET['email']) : '';
            if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                json_response(['success' => false, 'message' => 'Invalid email provided.'], 400);
            }
            $user = Database::findUserByEmail($email);
            json_response(['success' => true, 'is_registered' => (bool)$user]);
            break;

        case 'check_auth':
            $email = isset($_GET['email']) ? trim($_GET['email']) : '';
            if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                json_response(['success' => false, 'message' => 'Invalid email provided.'], 400);
            }
            $is_authorized = Database::isEmailAuthorized($email);
            json_response(['success' => true, 'is_authorized' => $is_authorized]);
            break;

        case 'register':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                json_response(['success' => false, 'message' => 'Invalid request method.'], 405);
            }
            $input = json_decode(file_get_contents('php://input'), true);
            $email = isset($input['email']) ? trim($input['email']) : '';
            $password = isset($input['password']) ? $input['password'] : '';

            if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL) || empty($password)) {
                json_response(['success' => false, 'message' => 'Email and password are required.'], 400);
            }
            if (strlen($password) < 6) {
                json_response(['success' => false, 'message' => 'Password must be at least 6 characters long.'], 400);
            }
            if (!Database::isEmailAuthorized($email)) {
                json_response(['success' => false, 'message' => 'This email is not authorized to register.'], 403);
            }
            if (Database::findUserByEmail($email)) {
                json_response(['success' => false, 'message' => 'Email already registered.'], 409);
            }

            $password_hash = password_hash($password, PASSWORD_DEFAULT);
            $user_id = Database::createUser($email, $password_hash);

            if ($user_id) {
                json_response(['success' => true, 'message' => 'User registered successfully.'], 201);
            } else {
                json_response(['success' => false, 'message' => 'Failed to register user.'], 500);
            }
            break;

        case 'login':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                json_response(['success' => false, 'message' => 'Invalid request method.'], 405);
            }
            $input = json_decode(file_get_contents('php://input'), true);
            $email = isset($input['email']) ? trim($input['email']) : '';
            $password = isset($input['password']) ? $input['password'] : '';

            if (empty($email) || empty($password)) {
                json_response(['success' => false, 'message' => 'Email and password are required.'], 400);
            }

            $user = Database::findUserByEmail($email);
            if (!$user || !password_verify($password, $user['password'])) {
                json_response(['success' => false, 'message' => 'Invalid credentials.'], 401);
            }

            // Generate and save token
            $token = bin2hex(random_bytes(32));
            $expires_at = date('Y-m-d H:i:s', time() + (3600 * 24 * 7)); // 7 days
            Database::updateUserToken($user['id'], $token, $expires_at);

            json_response(['success' => true, 'data' => ['token' => $token]]);
            break;

        case 'process_email':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                json_response(['success' => false, 'message' => 'Invalid request method.'], 405);
            }
            verify_worker_secret();

            $from = isset($_POST['from']) ? trim($_POST['from']) : '';
            $to = isset($_POST['to']) ? trim($_POST['to']) : '';
            $subject = isset($_POST['subject']) ? trim($_POST['subject']) : '';
            $body = isset($_POST['body']) ? trim($_POST['body']) : '';

            if (empty($from)) {
                 json_response(['success' => false, 'message' => 'Sender email is required.'], 400);
            }

            $user = Database::findUserByEmail($from);
            if (!$user) {
                json_response(['success' => false, 'message' => 'Sender is not a registered user.'], 403);
            }

            if (Database::saveEmail($user['id'], $from, $to, $subject, $body)) {
                json_response(['success' => true, 'message' => 'Email processed successfully.']);
            } else {
                json_response(['success' => false, 'message' => 'Failed to save email.'], 500);
            }
            break;

        default:
            json_response(['success' => false, 'message' => 'Invalid action specified.'], 400);
            break;
    }
} catch (Exception $e) {
    error_log("API Unhandled Exception: " . $e->getMessage());
    json_response(['success' => false, 'message' => 'An internal server error occurred.'], 500);
}
?>