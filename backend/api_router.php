<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

// CORS headers
require_once __DIR__ . '/cors.php';

// Pre-flight request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

require_once __DIR__ . '/db_connection.php';
require_once __DIR__ . '/api/auth.php';
require_once __DIR__ . '/receive_email.php';
require_once __DIR__ . '/auth_middleware.php';

// Basic routing
$endpoint = $_GET['endpoint'] ?? '';
$action = $_GET['action'] ?? '';

// Authenticated actions
$authenticated_actions = ['get_emails'];

if (in_array($action, $authenticated_actions)) {
    $user_id = validateToken();
}

switch ($endpoint) {
    case 'auth':
        handleAuthActions($action);
        break;
    case 'email':
        handleEmailActions($action, $user_id ?? null);
        break;
    default:
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Endpoint not found']);
}

function handleAuthActions($action) {
    switch ($action) {
        case 'register':
            register();
            break;
        case 'login':
            login();
            break;
        case 'check_auth':
            checkAuth();
            break;
        default:
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Action not found']);
    }
}

function handleEmailActions($action, $user_id) {
    switch ($action) {
        case 'is_user_registered':
            isUserRegistered();
            break;
        case 'process_email':
            processEmail();
            break;
        case 'get_emails':
            getEmails($user_id);
            break;
        default:
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Action not found']);
    }
}

function getEmails($user_id) {
    $conn = getDbConnection();
    $stmt = $conn->prepare("SELECT * FROM emails WHERE user_id = ? ORDER BY received_at DESC");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $emails = $result->fetch_all(MYSQLI_ASSOC);

    echo json_encode(['success' => true, 'emails' => $emails]);
}
