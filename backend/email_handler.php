<?php
// backend/email_handler.php
// Contains functions to handle incoming emails and parse their content.

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/db_operations.php';

/**
 * Processes an incoming email and attempts to extract bill information.
 * @param PDO $pdo The PDO database connection object.
 * @param string $rawEmail The full raw content of the email.
 * @param string $emailSubject The subject of the email.
 * @param string $recipientEmail The email address to which the email was sent.
 * @return array A result array indicating success or failure and any extracted data.
 */
function processIncomingEmail(PDO $pdo, string $rawEmail, string $emailSubject, string $recipientEmail):
    array
{
    // --- Step 1: Identify User by Recipient Email ---
    // In a real scenario, you'd likely have a more robust way to link recipient emails
    // to user accounts (e.g., unique email aliases per user).
    // For this example, we'll assume the recipient email is directly linked to a user's registered email.
    $user = fetchOne($pdo, "SELECT id FROM users WHERE email = :email", [':email' => $recipientEmail]);

    if (!$user) {
        // If no user is found for the recipient email, we cannot process it.
        // Log this or send an alert in a real application.
        return ['success' => false, 'message' => 'No user found for recipient email.'];
    }
    $userId = $user['id'];

    // --- Step 2: Determine if it's a lottery email ---
    $isLottery = (stripos($emailSubject, 'lottery') !== false || stripos($rawEmail, 'lottery') !== false);

    $billId = null;
    try {
        // --- Step 3: Save the raw email as a bill entry ---
        // Initially save as 'unprocessed'. AI will update status later.
        $billId = insert($pdo, 'bills', [
            'user_id' => $userId,
            'subject' => $emailSubject,
            'raw_email' => $rawEmail,
            'is_lottery' => $isLottery ? 1 : 0,
            'status' => 'unprocessed'
        ]);

        if (!$billId) {
            return ['success' => false, 'message' => 'Failed to save raw email as bill.'];
        }

        return ['success' => true, 'message' => 'Email saved as bill, awaiting AI processing.', 'billId' => $billId, 'userId' => $userId, 'isLottery' => $isLottery];

    } catch (PDOException $e) {
        error_log("Database error saving email: " . $e->getMessage());
        return ['success' => false, 'message' => 'Database error: ' . $e->getMessage()];
    }
}

/**
 * Updates a bill record after AI processing.
 * @param PDO $pdo The PDO database connection object.
 * @param int $billId The ID of the bill to update.
 * @param array $aiParsedData Associative array containing data parsed by AI (e.g., amount, due_date, lottery_numbers).
 * @param string $status The new status of the bill (e.g., 'processed', 'error').
 * @return bool True on success, false on failure.
 */
function updateBillAfterAiProcessing(PDO $pdo, int $billId, array $aiParsedData, string $status = 'processed'): bool
{
    $dataToUpdate = ['status' => $status];

    if (isset($aiParsedData['amount'])) {
        $dataToUpdate['amount'] = $aiParsedData['amount'];
    }
    if (isset($aiParsedData['due_date'])) {
        $dataToUpdate['due_date'] = $aiParsedData['due_date'];
    }
    if (isset($aiParsedData['lottery_numbers'])) {
        $dataToUpdate['lottery_numbers'] = $aiParsedData['lottery_numbers'];
    }

    try {
        $affectedRows = update($pdo, 'bills', $dataToUpdate, 'id = :bill_id', [':bill_id' => $billId]);
        return $affectedRows > 0;
    } catch (PDOException $e) {
        error_log("Database error updating bill after AI processing: " . $e->getMessage());
        return false;
    }
}

/**
 * Saves lottery results.
 * @param PDO $pdo The PDO database connection object.
 * @param string $drawDate The date of the lottery draw (YYYY-MM-DD).
 * @param string $winningNumbers A comma-separated string of winning numbers.
 * @return int|false The ID of the inserted result, or false on failure.
 */
function saveLotteryResults(PDO $pdo, string $drawDate, string $winningNumbers): int|false
{
    try {
        // Check if results for this date already exist
        $existing = fetchOne($pdo, "SELECT id FROM lottery_results WHERE draw_date = :draw_date", [':draw_date' => $drawDate]);
        if ($existing) {
            // Update existing entry
            update($pdo, 'lottery_results', [
                'winning_numbers' => $winningNumbers
            ], 'id = :id', [':id' => $existing['id']]);
            return $existing['id']; // Return the ID of the updated row
        } else {
            // Insert new entry
            return insert($pdo, 'lottery_results', [
                'draw_date' => $drawDate,
                'winning_numbers' => $winningNumbers
            ]);
        }
    } catch (PDOException $e) {
        error_log("Database error saving lottery results: " . $e->getMessage());
        return false;
    }
}
