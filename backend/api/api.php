<?php
// backend/api/api.php

// This script handles chat log uploads from two sources:
// 1. Logged-in users via the frontend (authenticated by PHP session).
// 2. The Cloudflare email worker (authenticated by a shared secret).

session_start();
require_once __DIR__ . '/database.php';

// --- Authentication and User ID Determination ---
$user_id = null;

// Case 1: Authenticated via Session (from frontend)
if (isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];
}
// Case 2: Authenticated via Worker
// The worker MUST send 'worker_secret' and 'user_email' as POST fields along with the file.
else if (isset($_POST['worker_secret']) && $_POST['worker_secret'] === 'A_VERY_SECRET_KEY' && isset($_POST['user_email'])) {
    $pdo_for_user = getDbConnection();
    $stmt = $pdo_for_user->prepare("SELECT id FROM users WHERE email = :email");
    $stmt->execute([':email' => $_POST['user_email']]);
    $user = $stmt->fetch();
    if ($user) {
        $user_id = $user['id'];
    }
}

// If no user_id could be determined, deny access.
if ($user_id === null) {
    http_response_code(401); // Unauthorized
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Authentication failed.']);
    exit();
}


// --- Script Main Logic ---

ini_set('display_errors', 1);
error_reporting(E_ALL);
$pdo = getDbConnection();

function saveChatLog(PDO $pdo, int $user_id, string $filename, array $parsedData) {
    $sql = "INSERT INTO chat_logs (user_id, filename, parsed_data) VALUES (:user_id, :filename, :parsed_data)";
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':user_id' => $user_id, ':filename' => $filename, ':parsed_data' => json_encode($parsedData)]);
    } catch (PDOException $e) {
        http_response_code(500);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Failed to save chat log.', 'error' => $e->getMessage()]);
        exit();
    }
}

header('Content-Type: application/json');
$response = ['success' => false, 'message' => 'Unknown error.'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_FILES['chat_file']) && $_FILES['chat_file']['error'] === UPLOAD_ERR_OK) {

        $file = $_FILES['chat_file'];
        $fileName = $file['name'];
        $fileTmpName = $file['tmp_name'];

        // (File validation can be added here if needed)

        $fileContent = file_get_contents($fileTmpName);

        $parsedData = [];
        $rawContentPreview = '';
        if ($fileContent) {
            $rawContentPreview = mb_substr($fileContent, 0, 1000, 'UTF-8');
            $lines = explode("\n", $fileContent);
            $currentMessage = null;
            foreach ($lines as $line) {
                $line = trim($line);
                if (empty($line)) continue;
                $pattern = '/^\[(\d{1,4}[-\/\.]\d{1,2}[-\/\.]\d{1,4}),?\s+(\d{1,2}:\d{1,2}(?::\d{1,2})?\s*(?:AM|PM)?)\]\s+([^:]+):\s+(.*)$/U';
                if (preg_match($pattern, $line, $matches)) {
                    if ($currentMessage) $parsedData[] = $currentMessage;
                    $currentMessage = ['Date' => trim($matches[1]), 'Time' => trim($matches[2]), 'Sender' => trim($matches[3]), 'Message' => trim($matches[4])];
                } else if ($currentMessage) {
                    $currentMessage['Message'] .= "\n" . $line;
                }
            }
            if ($currentMessage) $parsedData[] = $currentMessage;
        }

        if (!empty($parsedData)) {
            saveChatLog($pdo, $user_id, $fileName, $parsedData);
        }

        $response = [
            'success' => true,
            'message' => 'File uploaded and parsed successfully.',
            'fileName' => $fileName,
            'rawContent' => $rawContentPreview,
            'parsedData' => $parsedData
        ];

    } else {
        $response['message'] = 'No file uploaded or an upload error occurred. Code: ' . ($_FILES['chat_file']['error'] ?? 'Unknown');
    }
} else {
    $response['message'] = 'Only POST requests are accepted.';
    http_response_code(405);
}

echo json_encode($response);
?>
