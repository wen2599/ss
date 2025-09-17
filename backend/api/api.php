<?php
// backend/api/api.php
// Handles bet submissions from file uploads and the email worker.

session_start();
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/database.php';
require_once __DIR__ . '/parser.php';
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/email_helper.php'; // Include the new email helper

header('Content-Type: application/json');
$response = ['success' => false, 'message' => 'An unknown error occurred.'];

// --- Authentication and User ID Determination ---
$user_id = null;
$is_from_worker = false;

// Case 1: Authenticated via Session (from frontend)
if (isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];
}
// Case 2: Authenticated via Worker
else if (isset($_POST['worker_secret']) && $_POST['worker_secret'] === 'A_VERY_SECRET_KEY' && isset($_POST['user_email'])) {
    $pdo_for_user = getDbConnection();
    $stmt = $pdo_for_user->prepare("SELECT id FROM users WHERE email = :email");
    $stmt->execute([':email' => $_POST['user_email']]);
    $user = $stmt->fetch();
    if ($user) {
        $user_id = $user['id'];
        $is_from_worker = true;
    }
}

if ($user_id === null) {
    http_response_code(401); // Unauthorized
    $response['message'] = 'Authentication failed.';
    echo json_encode($response);
    exit();
}

// --- Main Logic ---
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); // Method Not Allowed
    $response['message'] = 'Only POST requests are accepted.';
    echo json_encode($response);
    exit();
}

try {
    $pdo = getDbConnection();

    // Determine content and issue number
    $betContent = '';
    $issue_number = null;
    $original_filename = 'N/A';

    if (isset($_FILES['bet_file'])) { // From frontend upload
        if ($_FILES['bet_file']['error'] !== UPLOAD_ERR_OK) {
            throw new Exception('File upload error code: ' . $_FILES['bet_file']['error']);
        }
        $betContent = file_get_contents($_FILES['bet_file']['tmp_name']);
        $issue_number = $_POST['issue_number'] ?? null;
        $original_filename = $_FILES['bet_file']['name'];

    } else if ($is_from_worker) { // From email worker
        // We'll define the worker to send 'bet_content' and 'issue_number'
        $betContent = $_POST['bet_content'] ?? '';
        $issue_number = $_POST['issue_number'] ?? null;
        $original_filename = 'Email Bet from ' . $_POST['user_email'];
    }

    if (empty($betContent)) {
        throw new Exception('No betting content provided.');
    }
    if (empty($issue_number)) {
        throw new Exception('Issue number is required.');
    }

    // Use the new parser
    $parsed_bets = parseBets($betContent, $pdo);

    if (empty($parsed_bets)) {
        throw new Exception('Could not parse any bets from the provided content.');
    }

    // Save the bet to the database
    $sql = "INSERT INTO bets (user_id, issue_number, original_content, bet_data, status) VALUES (:user_id, :issue_number, :original_content, :bet_data, 'unsettled')";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':user_id' => $user_id,
        ':issue_number' => $issue_number,
        ':original_content' => $betContent,
        ':bet_data' => json_encode($parsed_bets)
    ]);

    // If the bet came from the email worker, send a confirmation email
    if ($is_from_worker) {
        $recipient_email = $_POST['user_email'];
        $subject = "投注确认 (期号: {$issue_number})";
        $body = "我们已经收到您的投注内容，详情如下：\n\n" . $betContent;
        send_confirmation_email($recipient_email, $subject, $body);
    }

    $response = [
        'success' => true,
        'message' => 'Bet submitted successfully.',
        'fileName' => $original_filename,
        'issueNumber' => $issue_number,
        'parsedBets' => $parsed_bets
    ];

} catch (Exception $e) {
    http_response_code(400); // Bad Request
    $response['message'] = 'Failed to process bet: ' . $e->getMessage();
}

echo json_encode($response);
?>
