<?php
// backend/process_email_ai.php
// This script is intended to be run as a cron job or triggered by a worker.
// It fetches unprocessed emails, sends them to AI for parsing, and updates the database.

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/db_operations.php';
require_once __DIR__ . '/email_handler.php';
require_once __DIR__ . '/gemini_ai_helper.php';     // For Gemini AI integration
require_once __DIR__ . '/cloudflare_ai_helper.php'; // For Cloudflare AI integration

// Prevent direct web access, only allow CLI or authenticated worker calls.
// In a real scenario, implement a secure token check for HTTP_X_WORKER_AUTH
// For now, allow CLI and simple browser access with ADMIN_SECRET for testing.
if (php_sapi_name() !== 'cli') {
    $adminSecret = $_GET['secret'] ?? '';
    if (!isset($_ENV['ADMIN_SECRET']) || $adminSecret !== $_ENV['ADMIN_SECRET']) {
         http_response_code(403);
         echo json_encode(['error' => 'Access denied.']);
         exit();
    }
}

header('Content-Type: application/json');

// --- Configuration for AI Service ---
// Choose which AI service to use. Read from .env, default to 'GEMINI'.
$activeAiService = $_ENV['ACTIVE_AI_SERVICE'] ?? 'GEMINI'; 

echo json_encode(['message' => 'Starting email AI processing...']);

try {
    // Fetch unprocessed bills.
    $unprocessedBills = fetchAll($pdo, "SELECT id, user_id, subject, raw_email, is_lottery FROM bills WHERE status = 'unprocessed'");

    if (empty($unprocessedBills)) {
        echo json_encode(['message' => 'No unprocessed emails found.']);
        exit();
    }

    foreach ($unprocessedBills as $bill) {
        $billId = $bill['id'];
        $rawEmailContent = $bill['raw_email'];
        $isLottery = (bool)$bill['is_lottery'];

        $parsedData = null;

        // Call the active AI service.
        if ($activeAiService === 'GEMINI') {
            $parsedData = parseEmailWithGemini($rawEmailContent);
        } elseif ($activeAiService === 'CLOUDFLARE') {
            $parsedData = parseEmailWithCloudflareAI($rawEmailContent);
        } else {
            error_log("Unknown AI service configured: " . $activeAiService);
        }
        
        if ($parsedData) {
            $status = 'processed';

            // Handle lottery results specifically
            if ($isLottery && isset($parsedData['lottery_numbers']) && !empty($parsedData['lottery_numbers'])) {
                // For lottery emails, the due_date might represent the draw_date
                $drawDate = $parsedData['due_date'] ?? date('Y-m-d'); 
                saveLotteryResults($pdo, $drawDate, $parsedData['lottery_numbers']);
            }

            // Update the bill with parsed data
            $updateSuccess = updateBillAfterAiProcessing($pdo, $billId, $parsedData, $status);
            if ($updateSuccess) {
                echo json_encode(['message' => "Bill #{$billId} processed by AI and updated."]);
            } else {
                error_log("Failed to update bill #{$billId} after AI processing.");
                updateBillAfterAiProcessing($pdo, $billId, [], 'error_update'); // Mark as error
            }
        } else {
            error_log("AI failed to parse email for bill #{$billId}.");
            updateBillAfterAiProcessing($pdo, $billId, [], 'error_ai_parse'); // Mark as error
        }
    }

    echo json_encode(['message' => 'Email AI processing completed.']);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error during AI processing: ' . $e->getMessage()]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'General error during AI processing: ' . $e->getMessage()]);
}
