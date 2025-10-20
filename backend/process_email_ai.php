<?php
require_once __DIR__ . '/bootstrap.php';

write_log("------ process_email_ai.php Entry Point ------");

// --- Authentication Check ---
if (!isset($_SESSION['user_id'])) {
    json_response('error', 'You must be logged in.', 401);
}

// --- Input Validation ---
$data = json_decode(file_get_contents('php://input'), true);
$emailId = $data['email_id'] ?? null;

if (!$emailId) {
    json_response('error', 'Email ID is required.', 400);
}

try {
    $userId = $_SESSION['user_id'];
    $pdo = get_db_connection();

    // 1. Fetch the email content from the database
    $stmt = $pdo->prepare("SELECT html_content FROM emails WHERE id = ? AND user_id = ?");
    $stmt->execute([$emailId, $userId]);
    $email = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$email) {
        json_response('error', 'Email not found or you do not have permission to access it.', 404);
    }

    $htmlContent = $email['html_content'];

    // 2. Send the content to the Cloudflare Worker for AI processing
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
        write_log("AI worker failed with code {$http_code}: " . $workerResponse);
        json_response('error', 'Failed to process email with AI worker.', 502);
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
    json_response('success', $aiData);

} catch (PDOException $e) {
    write_log("Database error in process_email_ai.php: " . $e->getMessage());
    json_response('error', 'Database error: ' . $e->getMessage(), 500);
} catch (Exception $e) {
    write_log("Unexpected error in process_email_ai.php: " . $e->getMessage());
    json_response('error', 'An unexpected error occurred: ' . $e->getMessage(), 500);
}

write_log("------ process_email_ai.php Exit Point ------");
