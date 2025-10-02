<?php
// Action: Process an incoming email, parse it for bets, and save it as a bill.

require_once __DIR__ . '/../init.php'; // Centralized initialization
require_once __DIR__ . '/../vendor/autoload.php'; // Composer autoloader

use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use App\Lib\BetCalculator;
use App\Lib\GeminiCorrectionService;

// --- Logger Setup ---
$logLevel = Logger::toMonologLevel($_ENV['LOG_LEVEL'] ?? 'INFO');
$log = new Logger('email_upload');
$log->pushHandler(new StreamHandler(__DIR__ . '/../app.log', $logLevel));

// --- Constants ---
define('UPLOAD_DIR', __DIR__ . '/../uploads/');

// --- Helper Functions (specific to this action) ---

// (Helper functions like handle_attachments, smart_convert_encoding, etc., remain the same)
function handle_attachments($user_id) {
    // ... function content from original file
}
function smart_convert_encoding($text, $prefer_charset = null) {
    // ... function content from original file
}
function get_plain_text_body_from_email($raw_email, &$detected_charset = null) {
    // ... function content from original file
}

// --- Main Execution Logic ---
try {
    // 1. Validate Request
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Method Not Allowed', 405);
    }
    $worker_secret = $_ENV['WORKER_SECRET'] ?? '';
    if (!$worker_secret || !isset($_POST['worker_secret']) || $_POST['worker_secret'] !== $worker_secret) {
        throw new Exception('Access denied.', 403);
    }
    if (!isset($_POST['user_email']) || !isset($_FILES['raw_email_file'])) {
        throw new Exception('Missing required fields from worker.', 400);
    }
    if ($_FILES['raw_email_file']['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('File upload error from worker.', 400);
    }

    // 2. Fetch User and Raw Email Content
    $user_email = $_POST['user_email'];
    $raw_email_content = file_get_contents($_FILES['raw_email_file']['tmp_name']);
    if ($raw_email_content === false) {
        throw new Exception('Could not read uploaded email file.', 500);
    }

    $stmt = $pdo->prepare("SELECT id, winning_rate FROM users WHERE email = :email");
    $stmt->execute([':email' => $user_email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$user) {
        throw new Exception('User not found for the provided email.', 404);
    }
    $user_id = $user['id'];
    $user_winning_rate = $user['winning_rate'] ?? 47.0;

    // 3. Process Email Body and Attachments
    $detected_charset = null;
    $text_body = get_plain_text_body_from_email($raw_email_content, $detected_charset);
    $text_body = smart_convert_encoding($text_body, $detected_charset);
    // ... (rest of the body/attachment processing logic remains the same)

    // 4. Calculate Bill
    $calculator = new BetCalculator($pdo, $log, $user_id); // Correctly instantiate with logger
    $parsed_bill = $calculator->calculateMulti($calculation_content);

    $status = 'unrecognized';
    $settlement_details = null;
    $total_cost = null;

    if ($parsed_bill !== null && !empty($parsed_bill['slips'])) {
        // ... (AI Correction logic remains the same, but replace write_log with $log->info)
        
        // ... (Lottery result fetching and settlement logic remains the same)
    }

    // 5. Save Bill to Database
    $sql = "INSERT INTO bills (user_id, raw_content, settlement_details, total_cost, status)
            VALUES (:user_id, :raw_content, :settlement_details, :total_cost, :status)";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':user_id' => $user_id,
        ':raw_content' => $text_body, // Simplified for brevity
        ':settlement_details' => $settlement_details,
        ':total_cost' => $total_cost,
        ':status' => $status
    ]);

    // 6. Send Success Response
    http_response_code(201);
    echo json_encode([
        'success' => true,
        'message' => 'Bill processed and saved successfully.',
        'status' => $status,
        'attachments' => $attachments_meta ?? []
    ]);

} catch (Exception $e) {
    $code = $e->getCode();
    // Ensure the HTTP status code is a valid one, default to 500
    if ($code < 400 || $code >= 600) {
        $code = 500;
    }
    http_response_code($code);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
    $log->error("Error in email_upload.php: " . $e->getMessage(), ['exception' => $e]);
}

?>