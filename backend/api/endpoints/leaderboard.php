<?php
/**
 * Handles leaderboard-related endpoints:
 * - /get_leaderboard
 */
switch ($endpoint) {
    case 'get_leaderboard':
        if ($request_method !== 'GET') {
            Response::send_json_error(405, 'Method Not Allowed');
            break;
        }

        try {
            $stmt = $db->prepare("SELECT display_id, points FROM users ORDER BY points DESC LIMIT 10");
            $stmt->execute();
            $leaderboard = $stmt->fetchAll();
            Response::send_json(['success' => true, 'leaderboard' => $leaderboard]);
        } catch (Exception $e) {
            Response::send_json_error(500, 'Failed to get leaderboard: ' . $e->getMessage());
        }
        break;
}
