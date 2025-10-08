<?php
// backend/jobs/process_emails.php

ini_set('display_errors', 1);
error_reporting(E_ALL);
ini_set('max_execution_time', 300); // 5 minutes

require_once __DIR__ . '/../bootstrap.php';

// --- Lock Mechanism to prevent concurrent runs ---
$lock_file = sys_get_temp_dir() . '/process_emails.lock';
$lock_handle = fopen($lock_file, 'c');

if (!flock($lock_handle, LOCK_EX | LOCK_NB)) {
    echo "--- Another instance of the email processing job is already running. Exiting. ---\n";
    exit;
}

// Register a function to release the lock on script exit
register_shutdown_function(function() use ($lock_handle, $lock_file) {
    flock($lock_handle, LOCK_UN); // Release the lock
    fclose($lock_handle);
    if (file_exists($lock_file)) {
        unlink($lock_file);
    }
    echo "--- Lock released. Email Processing Job Finished ---\n";
});

echo "--- Lock acquired. Email Processing Job Started ---\n";

$conn = get_db_connection();
if (!$conn) {
    echo "Error: Database connection failed.\n";
    exit(1); // The shutdown function will handle the lock release
}

// 1. Fetch the AI prompt template
$template_name = 'betting_slip_parser';
$stmt_template = $conn->prepare("SELECT prompt_text FROM ai_templates WHERE name = ?");
$stmt_template->bind_param("s", $template_name);
$stmt_template->execute();
$result_template = $stmt_template->get_result();
if (!($template = $result_template->fetch_assoc())) {
    echo "Error: AI template '{$template_name}' not found.\n";
    $conn->close();
    exit(1);
}
$ai_prompt = $template['prompt_text'];
$stmt_template->close();
echo "Successfully loaded AI template: {$template_name}\n";

// 2. Find new emails to process
$sql_new_emails = "SELECT e.id, e.body_text FROM emails e LEFT JOIN betting_slips bs ON e.id = bs.email_id WHERE bs.id IS NULL ORDER BY e.received_at ASC LIMIT 10;";
$result_new_emails = $conn->query($sql_new_emails);

if ($result_new_emails->num_rows === 0) {
    echo "No new emails to process.\n";
    $conn->close();
    exit(0);
}

echo "Found " . $result_new_emails->num_rows . " new emails to process.\n";

// 3. Loop through each email
while ($email = $result_new_emails->fetch_assoc()) {
    $email_id = $email['id'];
    $raw_text = $email['body_text'] ?? '';

    echo "\nProcessing email ID: {$email_id}...\n";

    if (empty(trim($raw_text))) {
        echo "Skipping email ID: {$email_id} (empty body).\n";
        continue;
    }

    // 4. Call Gemini API
    echo "Calling Gemini API...\n";
    $api_response = call_gemini_api($ai_prompt, $raw_text);

    $parsed_data_json = null;
    $is_valid = false;
    $processing_error = null;

    if ($api_response['success']) {
        echo "API call successful. Validating JSON...\n";
        $parsed_data_json = $api_response['data'];
        json_decode($parsed_data_json);
        if (json_last_error() === JSON_ERROR_NONE) {
            echo "JSON is valid.\n";
            $is_valid = true;
        } else {
            echo "Warning: API returned non-JSON string.\n";
            $processing_error = "Invalid JSON response from AI: " . $parsed_data_json;
            $parsed_data_json = null; // Do not save invalid JSON
        }
    } else {
        echo "Error from Gemini API: " . $api_response['error'] . "\n";
        $processing_error = "AI API Error: " . $api_response['error'];
    }

    // 5. Save the result to the betting_slips table
    $stmt_insert_slip = $conn->prepare("INSERT INTO betting_slips (email_id, raw_text, parsed_data, is_valid, processing_error) VALUES (?, ?, ?, ?, ?)");
    // Note: We are now saving the original raw_text, not the modified one.
    $stmt_insert_slip->bind_param("issis", $email_id, $raw_text, $parsed_data_json, $is_valid, $processing_error);
    
    if ($stmt_insert_slip->execute()) {
        echo "Successfully created betting slip for email ID: {$email_id}. Valid: " . ($is_valid ? 'Yes' : 'No') . "\n";
    } else {
        echo "Error creating betting slip for email ID: {$email_id}. Error: " . $stmt_insert_slip->error . "\n";
    }
    $stmt_insert_slip->close();

    // Introduce a small delay to avoid overwhelming the API or server
    usleep(200000); // 200 milliseconds
}

$conn->close();
// The lock will be released by the registered shutdown function

?>