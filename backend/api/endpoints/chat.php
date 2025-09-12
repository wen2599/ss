<?php
/**
 * Handles chat-related endpoints:
 * - /send_message
 * - /get_messages
 */
switch ($endpoint) {
    case 'send_message':
        if ($request_method !== 'POST') {
            send_json_error(405, 'Method Not Allowed');
            break;
        }
        if (!isset($_SESSION['user_id'])) {
            send_json_error(401, '请先登录');
            break;
        }
        $user_id = $_SESSION['user_id'];
        $room_id = (int)($data['roomId'] ?? 0);
        $message = trim($data['message'] ?? '');

        if (!$room_id || empty($message)) {
            send_json_error(400, 'Missing required parameters.');
            break;
        }

        try {
            $stmt = $db->prepare("INSERT INTO chat_messages (room_id, user_id, message) VALUES (?, ?, ?)");
            $stmt->execute([$room_id, $user_id, $message]);
            echo json_encode(['success' => true, 'message' => 'Message sent.']);
        } catch (Exception $e) {
            send_json_error(500, 'Failed to send message: ' . $e->getMessage());
        }
        break;

    case 'get_messages':
        if ($request_method !== 'GET') {
            send_json_error(405, 'Method Not Allowed');
            break;
        }
        $room_id = (int)($_GET['roomId'] ?? 0);
        if (!$room_id) {
            send_json_error(400, 'Missing roomId');
            break;
        }

        try {
            $stmt = $db->prepare("SELECT cm.*, u.display_id FROM chat_messages cm JOIN users u ON cm.user_id = u.id WHERE cm.room_id = ? ORDER BY cm.created_at ASC");
            $stmt->execute([$room_id]);
            $messages = $stmt->fetchAll();
            echo json_encode(['success' => true, 'messages' => $messages]);
        } catch (Exception $e) {
            send_json_error(500, 'Failed to get messages: ' . $e->getMessage());
        }
        break;
}
