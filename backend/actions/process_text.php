<?php
/**
 * Action: process_text
 *
 * This script processes a given block of text to extract basic statistics,
 * such as character count, word count, and a list of keywords.
 * It is intended for internal use and requires user authentication.
 *
 * HTTP Method: POST
 *
 * Request Body (JSON):
 * - "emailText" (string): The block of text to be processed.
 *
 * Response:
 * - On success: { "success": true, "data": { "charCount": int, "wordCount": int, "keywords": [string] } }
 * - On error: { "success": false, "error": "Error message." }
 */

// The main router (index.php) handles initialization.
// Global variables $pdo and $log are available.

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); // Method Not Allowed
    $log->warning("Method not allowed for process_text.", ['method' => $_SERVER['REQUEST_METHOD']]);
    echo json_encode(['success' => false, 'error' => 'Method Not Allowed']);
    exit();
}

// 1. Authorization: Check if the user is logged in.
if (!isset($_SESSION['user_id'])) {
    http_response_code(401); // Unauthorized
    $log->warning("Unauthorized attempt to access process_text.", ['ip_address' => $_SERVER['REMOTE_ADDR']]);
    echo json_encode(['success' => false, 'error' => 'Authentication required.']);
    exit();
}

$data = json_decode(file_get_contents('php://input'), true);

// 2. Validation: Check for required field.
if (json_last_error() !== JSON_ERROR_NONE || !isset($data['emailText']) || !is_string($data['emailText'])) {
    http_response_code(400); // Bad Request
    $log->warning("Bad request to process_text: Invalid JSON or missing 'emailText' field.", ['user_id' => $_SESSION['user_id']]);
    echo json_encode(['success' => false, 'error' => 'Invalid request body. A string field "emailText" is required.']);
    exit();
}

$text = $data['emailText'];
$log->info("Processing text.", ['user_id' => $_SESSION['user_id'], 'text_length' => mb_strlen($text, 'UTF-8')]);

// 3. Processing Logic
$charCount = mb_strlen($text, 'UTF-8');

// To get an accurate word count, we replace punctuation with spaces.
$cleanedTextForWords = preg_replace('/[\p{P}\p{S}\s]+/u', ' ', $text);
$wordCount = count(array_filter(explode(' ', $cleanedTextForWords)));

// Extract keywords (words of 5+ letters or any sequence of Chinese characters)
preg_match_all('/([a-zA-Z]{5,})|([\p{Han}]+)/u', $text, $matches);
$keywords = array_values(array_unique(array_filter($matches[0])));

// 4. Send Response
http_response_code(200);
echo json_encode([
    'success' => true,
    'data' => [
        'charCount' => $charCount,
        'wordCount' => $wordCount,
        'keywords' => $keywords
    ]
], JSON_UNESCAPED_UNICODE);
?>