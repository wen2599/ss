<?php
require_once __DIR__ . '/api_header.php';

// --- Authentication Check ---
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'You must be logged in.']);
    exit;
}

// --- Input Validation ---
$data = json_decode(file_get_contents('php://input'), true);
$emailId = $data['email_id'] ?? null;

if (!$emailId) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Email ID is required.']);
    exit;
}

$userId = $_SESSION['user_id'];
$pdo = get_db_connection();

if (is_array($pdo) && isset($pdo['db_error'])) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $pdo['db_error']]);
    exit;
}

try {
    // 1. Fetch the email content from the database
    $stmt = $pdo->prepare("SELECT html_content FROM emails WHERE id = ? AND user_id = ?");
    $stmt->execute([$emailId, $userId]);
    $email = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$email) {
        http_response_code(404);
        echo json_encode(['status' => 'error', 'message' => 'Email not found or you do not have permission to access it.']);
        exit;
    }

    $htmlContent = $email['html_content'];

    // 2. Send the content to the Cloudflare Worker for AI processing
    // The worker URL is the public frontend URL, which will proxy to the worker.
    $workerUrl = 'https://ss.wenxiuxiu.eu.org/process-ai';
    $postData = json_encode(['email_content' => $htmlContent]);

    $ch = curl_init($workerUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Content-Length: ' . strlen($postData)
    ]);

    $workerResponse = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($http_code !== 200) {
        http_response_code(502); // Bad Gateway
        echo json_encode(['status' => 'error', 'message' => 'Failed to process email with AI worker.', 'worker_response' => $workerResponse]);
        exit;
    }

    $aiData = json_decode($workerResponse, true);

    // 3. Update the database with the extracted data
    $updateStmt = $pdo->prepare(
        "UPDATE emails SET
            vendor_name = :vendor_name,
            bill_amount = :bill_amount,
            currency = :currency,
            due_date = :due_date,
            invoice_number = :invoice_number,
            category = :category,
            is_processed = TRUE
         WHERE id = :id"
    );

    // Make sure all keys exist, defaulting to null if not provided by the AI
    $updateParams = [
        ':id' => $emailId,
        ':vendor_name' => $aiData['vendor_name'] ?? null,
        ':bill_amount' => !empty($aiData['bill_amount']) ? $aiData['bill_amount'] : null,
        ':currency' => $aiData['currency'] ?? null,
        ':due_date' => !empty($aiData['due_date']) ? $aiData['due_date'] : null,
        ':invoice_number' => $aiData['invoice_number'] ?? null,
        ':category' => $aiData['category'] ?? null,
    ];

    $updateStmt->execute($updateParams);

    // 4. Return the structured data to the frontend
    http_response_code(200);
    echo json_encode(['status' => 'success', 'data' => $aiData]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'An unexpected error occurred: ' . $e->getMessage()]);
}
?>