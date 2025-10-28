<?php
// backend/api/ai_process_email.php

require_once '../bootstrap.php';
require_once 'helpers.php'; // For sendJsonResponse

// --- Configuration ---
$gemini_api_key = getenv('GEMINI_API_KEY');
$gemini_api_endpoint = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash-latest:generateContent?key=' . $gemini_api_key;
$worker_secret = getenv('WORKER_SECRET');

// --- Input Validation ---
$request_data = json_decode(file_get_contents('php://input'), true);
$email_id = $request_data['email_id'] ?? null;
$client_secret = $request_data['secret'] ?? null;

if (empty($client_secret) || $client_secret !== $worker_secret) {
    sendJsonResponse(403, ['success' => false, 'message' => 'Forbidden: Invalid secret.']);
    exit;
}

if (empty($email_id)) {
    sendJsonResponse(400, ['success' => false, 'message' => 'Bad Request: Missing email_id.']);
    exit;
}

// --- Database Interaction ---
global $db_connection;

// 1. Fetch the raw email content from the database
$stmt = $db_connection->prepare("SELECT raw_email FROM emails WHERE id = ?");
$stmt->bind_param("i", $email_id);
$stmt->execute();
$result = $stmt->get_result();
$email = $result->fetch_assoc();
$stmt->close();

if (!$email || empty($email['raw_email'])) {
    sendJsonResponse(404, ['success' => false, 'message' => 'Email not found or content is empty.']);
    exit;
}

$raw_email_content = $email['raw_email'];

// --- AI Processing ---

$prompt = "You are an expert financial assistant. Your task is to extract structured data from the following email content. The email is a bill or invoice. Please extract the following fields: vendor_name, bill_amount (as a number), currency (e.g., USD, CNY), due_date (in YYYY-MM-DD format), invoice_number, and a category (e.g., 'Utilities', 'Subscription', 'Shopping', 'Travel'). If a field is not present, its value should be null. Provide the output in a clean JSON format. Do not include any explanatory text, only the JSON object.\n\nEmail Content:\n\"\"\"\n" . $raw_email_content . "\n\"\"\"";

$post_data = [
    'contents' => [
        [
            'parts' => [
                ['text' => $prompt]
            ]
        ]
    ]
];

// --- cURL Request to Gemini API ---
$ch = curl_init($gemini_api_endpoint);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($post_data));
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($http_code !== 200) {
    sendJsonResponse(502, ['success' => false, 'message' => 'Failed to get a valid response from AI model.', 'details' => $response]);
    exit;
}

$response_data = json_decode($response, true);
$ai_generated_text = $response_data['candidates'][0]['content']['parts'][0]['text'] ?? '';

// --- Data Extraction and Update ---

// Clean the AI response to get pure JSON
$json_match = [];
if (preg_match('/```json\s*([\s\S]+?)\s*```/', $ai_generated_text, $json_match)) {
    $json_response = $json_match[1];
} else {
    $json_response = $ai_generated_text;
}

$extracted_data = json_decode($json_response, true);

if (json_last_error() !== JSON_ERROR_NONE) {
    sendJsonResponse(500, ['success' => false, 'message' => 'Failed to parse JSON from AI response.', 'raw_response' => $ai_generated_text]);
    exit;
}

// 2. Update the 'emails' table with the structured data
$update_stmt = $db_connection->prepare("UPDATE emails SET 
    vendor_name = ?, 
    bill_amount = ?, 
    currency = ?, 
    due_date = ?, 
    invoice_number = ?, 
    category = ?, 
    status = 'processed' 
    WHERE id = ?");

$vendor = $extracted_data['vendor_name'] ?? null;
$amount = $extracted_data['bill_amount'] ?? null;
$currency = $extracted_data['currency'] ?? null;
$due_date = $extracted_data['due_date'] ?? null;
$invoice = $extracted_data['invoice_number'] ?? null;
$category = $extracted_data['category'] ?? null;

$update_stmt->bind_param("sdssssi", $vendor, $amount, $currency, $due_date, $invoice, $category, $email_id);

if ($update_stmt->execute()) {
    sendJsonResponse(200, ['success' => true, 'message' => 'Email processed and data updated successfully.', 'data' => $extracted_data]);
} else {
    sendJsonResponse(500, ['success' => false, 'message' => 'Database update failed.']);
}

$update_stmt->close();
$db_connection->close();
