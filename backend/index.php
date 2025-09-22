<?php
// Centralized entry point for all API requests

// 1. Centralized Headers & Session Management
// NOTE: session_start() must be called before any output.
session_start();

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");

// Handle pre-flight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(204);
    exit;
}

// All responses will be JSON
header('Content-Type: application/json');

// 2. Include Dependencies
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/lib/GameData.php';
require_once __DIR__ . '/lib/LotteryParser.php';
require_once __DIR__ . '/lib/BetCalculator.php';

// 3. Routing
// We will use a query parameter `action` to determine the route.
// e.g., /api/index.php?action=login
$action = $_GET['action'] ?? '';

// 4. Database Connection (centralized)
try {
    $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8", $db_user, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    error_log("DB connection error in router: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database connection failed.']);
    exit();
}


switch ($action) {
    // --- User Authentication ---
    case 'register':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['success' => false, 'error' => 'Only POST method is allowed for registration.']);
            exit();
        }

        $data = json_decode(file_get_contents("php://input"), true);

        if (!isset($data['email']) || !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Invalid or missing email.']);
            exit();
        }
        if (!isset($data['password']) || empty($data['password'])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Password is required.']);
            exit();
        }

        $email = $data['email'];
        $password_hash = password_hash($data['password'], PASSWORD_DEFAULT);

        try {
            $stmt = $pdo->prepare("INSERT INTO users (email, password) VALUES (:email, :password)");
            $stmt->execute([':email' => $email, ':password' => $password_hash]);

            http_response_code(201);
            echo json_encode(['success' => true, 'message' => 'User registered successfully.']);

        } catch (PDOException $e) {
            if ($e->errorInfo[1] == 1062) {
                http_response_code(409);
                echo json_encode(['success' => false, 'error' => 'User with this email already exists.']);
            } else {
                error_log("Registration DB error: " . $e->getMessage());
                http_response_code(500);
                echo json_encode(['success' => false, 'error' => 'A server error occurred during registration.']);
            }
            exit();
        }
        break;
    case 'login':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['success' => false, 'error' => 'Only POST method is allowed for login.']);
            exit();
        }

        $data = json_decode(file_get_contents("php://input"), true);

        if (!isset($data['email']) || !filter_var($data['email'], FILTER_VALIDATE_EMAIL) || !isset($data['password'])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Invalid input. Email and password are required.']);
            exit();
        }

        $email = $data['email'];
        $password = $data['password'];

        try {
            $stmt = $pdo->prepare("SELECT * FROM users WHERE email = :email");
            $stmt->execute([':email' => $email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user && password_verify($password, $user['password'])) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_email'] = $user['email'];

                http_response_code(200);
                echo json_encode([
                    'success' => true,
                    'message' => 'Login successful.',
                    'user' => ['id' => $user['id'], 'email' => $user['email']]
                ]);
            } else {
                http_response_code(401);
                echo json_encode(['success' => false, 'error' => 'Invalid email or password.']);
            }
        } catch (PDOException $e) {
            error_log("Login DB error: " . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'A server error occurred during login.']);
        }
        exit(); // Use exit to ensure no other code runs
        break;
    case 'logout':
        // Unset all of the session variables
        $_SESSION = array();

        // Destroy the session.
        session_destroy();

        echo json_encode(['success' => true, 'message' => 'Logged out successfully.']);
        exit();
        break;
    case 'check_session':
        if (isset($_SESSION['user_id']) && isset($_SESSION['user_email'])) {
            echo json_encode([
                'isAuthenticated' => true,
                'user' => [
                    'id' => $_SESSION['user_id'],
                    'email' => $_SESSION['user_email']
                ]
            ]);
        } else {
            echo json_encode(['isAuthenticated' => false]);
        }
        exit();
        break;

    // --- Bill Management ---
    case 'get_bills':
        if (!isset($_SESSION['user_id'])) {
            http_response_code(401);
            echo json_encode(['success' => false, 'error' => 'You must be logged in to view bills.']);
            exit();
        }
        $user_id = $_SESSION['user_id'];

        try {
            $sql = "SELECT id, total_cost, status, created_at FROM bills WHERE user_id = :user_id ORDER BY created_at DESC";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([':user_id' => $user_id]);

            $bills = $stmt->fetchAll(PDO::FETCH_ASSOC);

            http_response_code(200);
            echo json_encode(['success' => true, 'bills' => $bills]);

        } catch (PDOException $e) {
            error_log("Get Bills DB query error: " . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Failed to retrieve bills.']);
        }
        exit();
        break;

    // --- External Services & Processing ---
    case 'email_upload': // Previously api.php
        // This endpoint is for the email worker, so it uses POST and expects multipart/form-data
        if (!isset($_POST['worker_secret']) || $_POST['worker_secret'] !== $worker_secret) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Access denied.']);
            exit();
        }
        if (!isset($_POST['user_email']) || !filter_var($_POST['user_email'], FILTER_VALIDATE_EMAIL)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Invalid or missing user_email.']);
            exit();
        }
        if (!isset($_FILES['chat_file']) || $_FILES['chat_file']['error'] !== UPLOAD_ERR_OK) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'File upload error.']);
            exit();
        }

        $user_email = $_POST['user_email'];
        $file_tmp_path = $_FILES['chat_file']['tmp_name'];
        $raw_content = file_get_contents($file_tmp_path);

        if ($raw_content === false) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Could not read uploaded file.']);
            exit();
        }

        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = :email");
        $stmt->execute([':email' => $user_email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'User not found for the provided email.']);
            exit();
        }
        $user_id = $user['id'];

        $calculation_result = BetCalculator::calculate($raw_content);

        $status = 'unrecognized';
        $settlement_details = null;
        $total_cost = null;

        if ($calculation_result !== null) {
            $status = 'processed';
            $settlement_details = json_encode($calculation_result['breakdown'], JSON_UNESCAPED_UNICODE);
            $total_cost = $calculation_result['total_cost'];
        }

        try {
            $sql = "INSERT INTO bills (user_id, raw_content, settlement_details, total_cost, status)
                    VALUES (:user_id, :raw_content, :settlement_details, :total_cost, :status)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':user_id' => $user_id,
                ':raw_content' => $raw_content,
                ':settlement_details' => $settlement_details,
                ':total_cost' => $total_cost,
                ':status' => $status
            ]);

            http_response_code(201);
            echo json_encode([
                'success' => true,
                'message' => 'Bill processed and saved successfully.',
                'status' => $status
            ]);

        } catch (PDOException $e) {
            error_log("Bill insertion error: " . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Failed to save the bill to the database.']);
        }
        exit();
        break;
    case 'is_user_registered':
        if (!isset($_GET['worker_secret']) || $_GET['worker_secret'] !== $worker_secret) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Access denied. Invalid secret.']);
            exit();
        }
        if (!isset($_GET['email']) || !filter_var($_GET['email'], FILTER_VALIDATE_EMAIL)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Invalid or missing email parameter.']);
            exit();
        }

        $email = $_GET['email'];
        $is_registered = false;

        try {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE email = :email");
            $stmt->execute([':email' => $email]);

            if ($stmt->fetchColumn() > 0) {
                $is_registered = true;
            }
        } catch (PDOException $e) {
            error_log("User verification DB error: " . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'A server error occurred during verification.']);
            exit();
        }

        http_response_code(200);
        echo json_encode([
            'success' => true,
            'is_registered' => $is_registered
        ]);
        exit();
        break;
    case 'process_text': // Previously process.php
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['success' => false, 'error' => 'Only POST method is allowed for text processing.']);
            exit();
        }

        $data = json_decode(file_get_contents('php://input'), true);

        if (json_last_error() !== JSON_ERROR_NONE || !isset($data['emailText'])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Invalid JSON or missing "emailText" field.']);
            exit();
        }

        $text = $data['emailText'];
        $char_count = mb_strlen($text, 'UTF-8');
        $cleaned_text_for_words = preg_replace('/[\p{P}\p{S}\s]+/u', ' ', $text);
        $word_count = str_word_count($cleaned_text_for_words);

        preg_match_all('/([a-zA-Z]{5,})|([\p{Han}]+)/u', $text, $matches);
        $keywords = array_unique(array_filter($matches[0]));

        $response = [
            'success' => true,
            'data' => [
                'charCount' => $char_count,
                'wordCount' => $word_count,
                'keywords' => array_values($keywords)
            ]
        ];

        echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit();
        break;

    // --- Default ---
    default:
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Endpoint not found.']);
        break;
}

?>
