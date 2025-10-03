<?php
require_once __DIR__ . '/../init.php';

// --- Authentication Check ---
if (!isset($_SESSION['user_id'])) {
    json_response(['error' => 'You must be logged in to process emails.'], 401);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['error' => 'Only POST method is allowed.'], 405);
}

$data = json_decode(file_get_contents('php://input'), true);
$email_text = $data['text'] ?? null;

if (!$email_text || empty(trim($email_text))) {
    json_response(['error' => 'No email text provided.'], 400);
}

// --- Email Parsing Logic ---
function parse_email_text($text) {
    $parsed = [
        'from' => 'N/A',
        'to' => 'N/A',
        'subject' => 'N/A',
        'date' => 'N/A',
        'body' => ''
    ];

    // Split headers from body
    $parts = preg_split("/\r?\n\r?\n/", $text, 2);
    $header_block = $parts[0];
    $body_block = $parts[1] ?? '';

    // Regex for common headers
    $header_patterns = [
        'from'    => '/^From:\s*(.*)$/im',
        'to'      => '/^To:\s*(.*)$/im',
        'subject' => '/^Subject:\s*(.*)$/im',
        'date'    => '/^Date:\s*(.*)$/im'
    ];

    foreach ($header_patterns as $key => $pattern) {
        if (preg_match($pattern, $header_block, $matches)) {
            // Decode MIME encoded-word syntax (e.g., =?UTF-8?B?....?=)
            $parsed[$key] = trim(iconv_mime_decode($matches[1], 0, "UTF-8"));
        }
    }

    // Naively assign the rest as body
    $parsed['body'] = trim($body_block);

    return $parsed;
}

$processed_data = parse_email_text($email_text);

json_response($processed_data, 200);
?>