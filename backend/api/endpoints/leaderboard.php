<?php
/**
 * Handles leaderboard-related endpoints:
 * - /get_leaderboard
 */
switch ($endpoint) {
    case 'get_leaderboard':
        if ($request_method !== 'GET') {
            send_json_error(405, 'Method Not Allowed');
            break;
        }

        try {
            $stmt = $db->prepare("SELECT display_id, points FROM users ORDER BY points DESC LIMIT 10");
            $stmt->execute();
            $leaderboard = $stmt->fetchAll();
            echo json_encode(['success' => true, 'leaderboard' => $leaderboard]);
        } catch (Exception $e) {
            send_json_error(500, 'Failed to get leaderboard: ' . $e->getMessage());
        }
        break;
}
