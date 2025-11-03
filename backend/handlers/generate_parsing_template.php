<?php
// backend/handlers/generate_parsing_template.php

require_once __DIR__ . '/../utils/ai_handler.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    send_json_response(['status' => 'error', 'message' => 'Invalid request method.'], 405);
}

// User authentication is handled in api.php
if (!isset($current_user_id)) {
     send_json_response(['status' => 'error', 'message' => 'Authentication required.'], 401);
}

$input = json_decode(file_get_contents('php://input'), true);
$email_body = $input['email_body'] ?? null;
$template_name = $input['template_name'] ?? 'default';
$ai_provider = $input['ai_provider'] ?? 'cloudflare'; // Default to cloudflare

if (empty($email_body)) {
    send_json_response(['status' => 'error', 'message' => 'Email body is required.'], 400);
}

// --- Prompt for AI ---
$prompt = "请分析以下邮件内容，并生成一个PHP兼容的正则表达式（PCRE），用于从类似的邮件中提取投注信息。" .
          "正则表达式应该能够捕获每个时间点之后的所有文本，直到下一个时间点或邮件结束。" .
          "请只返回正则表达式本身，不要包含任何额外的解释或代码包装器。\n\n" .
          "邮件内容示例：\n" .
          "----------------\n" .
          $email_body .
          "\n----------------";

// Call the selected AI provider
switch ($ai_provider) {
    case 'gemini':
        $regex_template = call_gemini_api($prompt);
        break;
    case 'cloudflare':
    default:
        $regex_template = call_cloudflare_ai_api($prompt);
        break;
}

// --- Validate and Save the Template ---
// Basic validation: check if it's a valid regex
if (@preg_match($regex_template, '') === false) {
    send_json_response([
        'status' => 'error',
        'message' => 'AI returned an invalid regular expression.',
        'ai_response' => $regex_template
    ], 500);
}

// For simplicity, we will save the template to a file.
// A database would be a more robust solution for a multi-user system.
$template_path = __DIR__ . '/../utils/parsing_templates/';
if (!is_dir($template_path)) {
    mkdir($template_path, 0755, true);
}
file_put_contents($template_path . $template_name . '.regex', $regex_template);


send_json_response([
    'status' => 'success',
    'message' => 'Successfully generated and saved new parsing template.',
    'template_name' => $template_name,
    'regex' => $regex_template
]);
