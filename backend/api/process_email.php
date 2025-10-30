<?php
// File: backend/api/process_email.php
// Description: API endpoint to process an email and generate a template.

// Set header to return JSON
header('Content-Type: application/json');

// --- Includes ---
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/secrets.php'; // Needed for AiHandler
require_once __DIR__ . '/../services/AiHandler.php';

// --- Response Helper ---
function json_response($status_code, $data) {
    http_response_code($status_code);
    echo json_encode($data);
    exit();
}

// --- Main Logic ---

// 1. Check for POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(405, ['success' => false, 'error' => 'Method Not Allowed']);
}

// 2. Get and validate raw POST data
$raw_data = file_get_contents('php://input');
$request_data = json_decode($raw_data, true);

if (json_last_error() !== JSON_ERROR_NONE) {
    json_response(400, ['success' => false, 'error' => 'Invalid JSON payload.']);
}

$email_content = $request_data['email_content'] ?? null;
$ai_provider = $request_data['ai_provider'] ?? 'gemini'; // Default to Gemini

if (empty($email_content)) {
    json_response(400, ['success' => false, 'error' => 'Missing required field: email_content.']);
}

// 3. Connect to Database and Instantiate AiHandler
try {
    $conn = get_db_connection();
    $ai_handler = new AiHandler($conn);
} catch (Exception $e) {
    error_log("API process_email - DB/AI Handler instantiation failed: " . $e->getMessage());
    json_response(500, ['success' => false, 'error' => 'Server configuration error.']);
}

// 4. Call the AI Service
if ($ai_provider === 'cloudflare') {
    $result = $ai_handler->generateTemplateWithCloudflare($email_content);
} else {
    $result = $ai_handler->generateTemplateWithGemini($email_content);
}

if (!$result['success']) {
    json_response(502, [ // 502 Bad Gateway suggests an issue with an upstream service (the AI API)
        'success' => false, 
        'error' => 'Failed to generate template from AI service.', 
        'details' => $result['error']
    ]);
}

$generated_template = $result['text'];

// 5. Save the new template to the database
$template_name = 'Generated from Email - ' . date('Y-m-d H:i:s'); // Create a default name
$created_by_ai = $ai_provider; // Track which AI made it

$sql = "INSERT INTO email_templates (name, template_content, created_by_ai) VALUES (?, ?, ?)";
$stmt = $conn->prepare($sql);
if (!$stmt) {
    error_log("API process_email - DB statement prepare failed: " . $conn->error);
    json_response(500, ['success' => false, 'error' => 'Database statement preparation failed.']);
}

$stmt->bind_param('sss', $template_name, $generated_template, $created_by_ai);

if ($stmt->execute()) {
    $new_template_id = $stmt->insert_id;
    json_response(201, [ // 201 Created
        'success' => true, 
        'message' => 'Email processed and template created successfully!',
        'template_id' => $new_template_id,
        'template_name' => $template_name,
        'ai_provider' => $created_by_ai
    ]);
} else {
    error_log("API process_email - DB execute failed: " . $stmt->error);
    json_response(500, ['success' => false, 'error' => 'Failed to save the generated template to the database.']);
}

$stmt->close();
$conn->close();

?>