<?php
// Action: Process a block of text

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
?>
