
<?php
// backend/api.php

// --- CORS Configuration ---
// Allow requests from your specific frontend domain.
// Replace 'https://ss.wenxiuxiu.eu.org' with your actual frontend URL in a production environment.
header('Access-Control-Allow-Origin: https://ss.wenxiuxiu.eu.org');
// Allow common methods and headers.
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Handle pre-flight OPTIONS requests.
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    // End script execution after sending pre-flight headers.
    exit(0);
}

header('Content-Type: application/json');

// --- Environment Variable Loading ---
/**
 * Loads environment variables from a .env file.
 * @param string $path The path to the .env file.
 */
function load_env($path) {
    if (!file_exists($path)) {
        // Silently fail if .env is not found
        return;
    }
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) {
            continue; // Skip comments
        }
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

// Load .env file from the same directory
load_env(__DIR__ . '/.env');


// --- Configuration & Security ---
// Get the secret from environment variables. Fallback to a placeholder if not set.
$WORKER_SECRET = getenv('WORKER_SECRET') ?: 'YOUR_EMAIL_HANDLER_SECRET';


// --- Mock Data (for now) ---
$registered_users = [
    'test@example.com' => ['user_id' => 101, 'name' => 'Test User'],
    'another@example.com' => ['user_id' => 102, 'name' => 'Another User'],
];

// --- Request Routing ---
$method = $_SERVER['REQUEST_METHOD'];
$request_path = isset($_GET['request']) ? $_GET['request'] : '';

switch ($request_path) {
    case 'users/is-registered':
        handle_user_verification($method, $WORKER_SECRET, $registered_users);
        break;
    case 'emails':
        handle_email_submission($method, $WORKER_SECRET);
        break;
    case 'get-records':
        handle_get_records($method);
        break;
    default:
        http_response_code(404);
        echo json_encode(['status' => 'error', 'message' => 'Endpoint not found']);
        break;
}

// --- Function Implementations ---

function handle_user_verification($method, $secret, $users) {
    if ($method !== 'GET') {
        http_response_code(405);
        echo json_encode(['status' => 'error', 'message' => 'Method Not Allowed']);
        return;
    }
    $provided_secret = isset($_GET['worker_secret']) ? $_GET['worker_secret'] : '';
    $email = isset($_GET['email']) ? $_GET['email'] : '';
    if (!$secret || $provided_secret !== $secret) {
        http_response_code(403);
        echo json_encode(['status' => 'error', 'message' => 'Forbidden: Invalid worker secret']);
        return;
    }
    if (array_key_exists($email, $users)) {
        echo json_encode(['status' => 'success', 'data' => ['is_registered' => true, 'user_id' => $users[$email]['user_id']]]);
    } else {
        echo json_encode(['status' => 'success', 'data' => ['is_registered' => false, 'user_id' => null]]);
    }
}

function handle_email_submission($method, $secret) {
    if ($method !== 'POST') {
        http_response_code(405);
        echo json_encode(['status' => 'error', 'message' => 'Method Not Allowed']);
        return;
    }
    $json_data = file_get_contents('php://input');
    $data = json_decode($json_data, true);
    if (!$data || !isset($data['worker_secret']) || !$secret || $data['worker_secret'] !== $secret) {
        http_response_code(403);
        echo json_encode(['status' => 'error', 'message' => 'Forbidden: Invalid or missing worker secret in POST body']);
        return;
    }
    $email_body = isset($data['body']) ? $data['body'] : '';
    
    function extract_info($pattern, $text) {
        $plain_text = strip_tags(str_replace('<br>', "\n", $text));
        if (preg_match($pattern, $plain_text, $matches)) {
            return trim($matches[1]);
        }
        return null;
    }

    $structured_data = [
        'vendor' => extract_info('/供应商:\s*(.*)/i', $email_body),
        'invoice_number' => extract_info('/账单号:\s*(.*)/i', $email_body),
        'amount' => extract_info('/应付金额:\s*.*?([0-9.]+)/i', $email_body),
        'due_date' => extract_info('/截止日期:\s*(.*)/i', $email_body),
    ];

    $record_to_save = [
        'received_at' => date('c'),
        'from' => $data['from'],
        'subject' => $data['subject'],
        'user_id' => $data['user_id'],
        'extracted_data' => $structured_data,
        'original_body' => $email_body
    ];
    
    $data_file = 'data.json';
    $file_handle = fopen($data_file, 'c+');
    if (flock($file_handle, LOCK_EX)) {
        $current_content = fread($file_handle, filesize($data_file) > 0 ? filesize($data_file) : 1);
        $records = json_decode($current_content, true);
        if (!is_array($records)) {
            $records = [];
        }
        $records[] = $record_to_save;
        ftruncate($file_handle, 0);
        rewind($file_handle);
        fwrite($file_handle, json_encode($records, JSON_PRETTY_PRINT));
        flock($file_handle, LOCK_UN);
    } else {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Could not get file lock']);
        fclose($file_handle);
        return;
    }
    fclose($file_handle);
    echo json_encode(['status' => 'success', 'message' => 'Email data saved.', 'saved_record' => $record_to_save]);
}

function handle_get_records($method) {
    if ($method !== 'GET') {
        http_response_code(405);
        echo json_encode(['status' => 'error', 'message' => 'Method Not Allowed']);
        return;
    }
    $data_file = 'data.json';
    if (file_exists($data_file)) {
        $content = file_get_contents($data_file);
        header('Content-Type: application/json');
        echo $content;
    } else {
        echo json_encode([]);
    }
}
?>
