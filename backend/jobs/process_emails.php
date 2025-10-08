<?php
// backend/jobs/process_emails.php

ini_set('display_errors', 1);
error_reporting(E_ALL);

// Set a longer execution time for this script, as API calls can be slow.
ini_set('max_execution_time', 300); // 5 minutes

require_once __DIR__ . '/../bootstrap.php';

echo "--- Email Processing Job Started ---\n";

$conn = get_db_connection();
if (!$conn) {
    echo "Error: Database connection failed.\n";
    exit(1);
}

// 1. Fetch the AI prompt template from the database
$template_name = 'betting_slip_parser';
$stmt_template = $conn->prepare("SELECT prompt_text FROM ai_templates WHERE name = ?");
$stmt_template->bind_param("s", $template_name);
$stmt_template->execute();
$result_template = $stmt_template->get_result();
if (!($template = $result_template->fetch_assoc())) {
    echo "Error: AI template '{$template_name}' not found in the database.\n";
    $conn->close();
    exit(1);
}
$ai_prompt = $template['prompt_text'];
$stmt_template->close();

echo "Successfully loaded AI template: {$template_name}\n";

// 2. Find new emails that haven't been processed yet
// We join with betting_slips to find emails that do NOT have a corresponding slip.
$sql_new_emails = "
    SELECT e.id, e.body_text 
    FROM emails e
    LEFT JOIN betting_slips bs ON e.id = bs.email_id
    WHERE bs.id IS NULL
    ORDER BY e.received_at ASC
    LIMIT 10; -- Process in batches
";

$result_new_emails = $conn->query($sql_new_emails);

if ($result_new_emails->num_rows === 0) {
    echo "No new emails to process.\n";
    $conn->close();
    exit(0);
}

echo "Found " . $result_new_emails->num_rows . " new emails to process.\n";

// 3. Loop through each new email and process it
while ($email = $result_new_emails->fetch_assoc()) {
    $email_id = $email['id'];
    $raw_text = $email['body_text'] ?? '';

    echo "\nProcessing email ID: {$email_id}...\n";

    if (empty(trim($raw_text))) {
        echo "Skipping email ID: {$email_id} because its body is empty.\n";
        // Optionally, insert a slip with is_valid = false to mark it as processed
        continue;
    }

    // 4. Call the Gemini API to parse the email content
    echo "Calling Gemini API...\n";
    $api_response = call_gemini_api($ai_prompt, $raw_text);

    $parsed_data_json = null;
    $is_valid = false;

    if ($api_response['success']) {
        echo "API call successful. Parsing JSON...\n";
        $parsed_data_json = $api_response['data'];
        
        // Validate if the returned data is a valid JSON
        json_decode($parsed_data_json);
        if (json_last_error() === JSON_ERROR_NONE) {
            echo "JSON is valid.\n";
            $is_valid = true;
        } else {
            echo "Warning: API returned a string that is not valid JSON. Content: {$parsed_data_json}\n";
            // Store the invalid JSON as raw text for manual inspection
            $raw_text .= "\n\n--- AI RESPONSE (INVALID JSON) ---\n" . $parsed_data_json;
            $parsed_data_json = null; // Do not save invalid JSON in the JSON column
        }
    } else {
        echo "Error from Gemini API: " . $api_response['error'] . "\n";
        // Store the error in the raw text for debugging
        $raw_text .= "\n\n--- AI PROCESSING ERROR ---\n" . $api_response['error'];
    }

    // 5. Save the result to the betting_slips table
    $stmt_insert_slip = $conn->prepare("
        INSERT INTO betting_slips (email_id, raw_text, parsed_data, is_valid)
        VALUES (?, ?, ?, ?)
    ");
    $stmt_insert_slip->bind_param("issi", $email_id, $raw_text, $parsed_data_json, $is_valid);
    
    if ($stmt_insert_slip->execute()) {
        echo "Successfully created betting slip for email ID: {$email_id}. Valid: " . ($is_valid ? 'Yes' : 'No') . "\n";
    } else {
        echo "Error creating betting slip for email ID: {$email_id}. Error: " . $stmt_insert_slip->error . "\n";
    }
    $stmt_insert_slip->close();
}

$conn->close();
echo "\n--- Email Processing Job Finished ---\n";

?>