<?php
require_once __DIR__ . '/../bootstrap.php';

write_log("------ get_bills.php Entry Point ------");

$pdo = get_db_connection();

// Check if user is authenticated
if (!isset($_SESSION['user_id'])) {
    json_response('error', 'Unauthorized: User not logged in.', 401);
}

$userId = $_SESSION['user_id'];
$billId = $_GET['id'] ?? null;

try {
    if ($billId) {
        // Fetch a single bill by ID for the logged-in user
        $stmt = $pdo->prepare("SELECT id, user_id, email_id, bill_name, amount, due_date, status, created_at FROM bills WHERE id = ? AND user_id = ?");
        $stmt->execute([$billId, $userId]);
        $bill = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($bill) {
            json_response('success', ['bill' => $bill]);
        } else {
            write_log("Bill ID {$billId} not found for user {$userId} or unauthorized.");
            json_response('error', '未找到该账单或无权查看。', 404);
        }
    } else {
        // Fetch all bills for the logged-in user
        $stmt = $pdo->prepare("SELECT id, user_id, email_id, bill_name, amount, due_date, status, created_at FROM bills WHERE user_id = ? ORDER BY due_date DESC");
        $stmt->execute([$userId]);
        $bills = $stmt->fetchAll(PDO::FETCH_ASSOC);

        json_response('success', ['bills' => $bills]);
    }

} catch (PDOException $e) {
    write_log("Error fetching bills: " . $e->getMessage());
    json_response('error', 'An error occurred while fetching bills.', 500);
}

write_log("------ get_bills.php Exit Point ------");
