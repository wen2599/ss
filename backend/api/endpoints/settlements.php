<?php
// backend/api/endpoints/settlements.php

require_once __DIR__ . '/../db.php';

$db = get_db();
$endpoint = $_GET['endpoint'] ?? '';

if ($endpoint === 'settle_draw' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    // This should be an admin-only endpoint
    $data = json_decode(file_get_contents('php://input'), true);

    $draw_id = $data['draw_id'] ?? null;
    $winning_numbers = $data['winning_numbers'] ?? null;

    if (!$draw_id || !$winning_numbers || !is_array($winning_numbers)) {
        send_json_error(400, 'Missing or invalid required fields for settling a draw.');
    }

    $db->beginTransaction();
    try {
        // Update draw status and winning numbers
        $stmt = $db->prepare("UPDATE lottery_draws SET winning_numbers = ?, status = 'settled' WHERE id = ? AND status = 'closed'");
        $stmt->execute([json_encode($winning_numbers), $draw_id]);
        if ($stmt->rowCount() === 0) {
            throw new Exception("Draw not found or not in a closable state.");
        }

        // Fetch all bets for this draw
        $stmt = $db->prepare("SELECT * FROM bets WHERE draw_id = ? AND status = 'placed'");
        $stmt->execute([$draw_id]);
        $bets = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Define a simple prize structure (this can be expanded)
        // For simplicity, we'll use a fixed prize for matching all numbers.
        $prize_amount = 10000; // Example prize

        foreach ($bets as $bet) {
            $bet_numbers = json_decode($bet['bet_numbers'], true);
            sort($bet_numbers);
            sort($winning_numbers);

            $is_winner = ($bet_numbers == $winning_numbers);

            if ($is_winner) {
                $winnings = $bet['bet_amount'] * $prize_amount; // Simple multiplication, can be more complex

                // Update bet status and winnings
                $stmt = $db->prepare("UPDATE bets SET status = 'won', winnings = ? WHERE id = ?");
                $stmt->execute([$winnings, $bet['id']]);

                // Update user points
                $stmt = $db->prepare("UPDATE users SET points = points + ? WHERE id = ?");
                $stmt->execute([$winnings, $bet['user_id']]);
            } else {
                // Update bet status
                $stmt = $db->prepare("UPDATE bets SET status = 'lost' WHERE id = ?");
                $stmt->execute([$bet['id']]);
            }
        }

        $db->commit();

        echo json_encode(['success' => true, 'message' => 'Draw settled successfully.']);
    } catch (Exception $e) {
        $db->rollBack();
        send_json_error(500, 'Failed to settle draw.', $e->getMessage());
    }
} else {
    send_json_error(404, 'Settlement endpoint not found');
}
