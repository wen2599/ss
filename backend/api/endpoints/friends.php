<?php
/**
 * Handles friend-related endpoints:
 * - /add_friend
 * - /accept_friend
 * - /get_friends
 */
switch ($endpoint) {
    case 'add_friend':
        if ($request_method !== 'POST') {
            send_json_error(405, 'Method Not Allowed');
            break;
        }
        if (!isset($_SESSION['user_id'])) {
            send_json_error(401, '请先登录');
            break;
        }
        $user_id = $_SESSION['user_id'];
        $friend_id = (int)($data['friendId'] ?? 0);

        if (!$friend_id) {
            send_json_error(400, 'Missing friendId.');
            break;
        }

        if ($user_id === $friend_id) {
            send_json_error(400, 'You cannot add yourself as a friend.');
            break;
        }

        try {
            $stmt = $db->prepare("INSERT INTO friends (user_id, friend_id) VALUES (?, ?)");
            $stmt->execute([$user_id, $friend_id]);
            echo json_encode(['success' => true, 'message' => 'Friend request sent.']);
        } catch (Exception $e) {
            send_json_error(500, 'Failed to send friend request: ' . $e->getMessage());
        }
        break;

    case 'accept_friend':
        if ($request_method !== 'POST') {
            send_json_error(405, 'Method Not Allowed');
            break;
        }
        if (!isset($_SESSION['user_id'])) {
            send_json_error(401, '请先登录');
            break;
        }
        $user_id = $_SESSION['user_id'];
        $friend_id = (int)($data['friendId'] ?? 0);

        if (!$friend_id) {
            send_json_error(400, 'Missing friendId.');
            break;
        }

        try {
            $stmt = $db->prepare("UPDATE friends SET status = 'accepted' WHERE user_id = ? AND friend_id = ?");
            $stmt->execute([$friend_id, $user_id]);
            echo json_encode(['success' => true, 'message' => 'Friend request accepted.']);
        } catch (Exception $e) {
            send_json_error(500, 'Failed to accept friend request: ' . $e->getMessage());
        }
        break;

    case 'get_friends':
        if ($request_method !== 'GET') {
            send_json_error(405, 'Method Not Allowed');
            break;
        }
        if (!isset($_SESSION['user_id'])) {
            send_json_error(401, '请先登录');
            break;
        }
        $user_id = $_SESSION['user_id'];

        try {
            $stmt = $db->prepare("SELECT u.id, u.display_id, f.status FROM users u JOIN friends f ON u.id = f.friend_id WHERE f.user_id = ?");
            $stmt->execute([$user_id]);
            $friends = $stmt->fetchAll();
            echo json_encode(['success' => true, 'friends' => $friends]);
        } catch (Exception $e) {
            send_json_error(500, 'Failed to get friends: ' . $e->getMessage());
        }
        break;
}
