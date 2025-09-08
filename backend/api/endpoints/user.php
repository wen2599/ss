<?php
/**
 * Handles user-related endpoints:
 * - /transfer_points
 * - /find_user
 */
switch ($endpoint) {
    case 'transfer_points':
        if ($request_method !== 'POST') {
            send_json_error(405, 'Method Not Allowed');
            break;
        }
        if (!isset($_SESSION['user_id'])) send_json_error(401, '请先登录');
        $sender_id = $_SESSION['user_id'];
        $recipient_display_id = $data['recipientId'] ?? null;
        $amount = (int)($data['amount'] ?? 0);
        if (!$recipient_display_id || $amount <= 0) send_json_error(400, '无效的接收人ID或金额');
        $db->begin_transaction();
        try {
            $stmt = $db->prepare("SELECT points FROM users WHERE id = ? FOR UPDATE");
            $stmt->bind_param('i', $sender_id);
            $stmt->execute();
            $sender = $stmt->get_result()->fetch_assoc();
            if (!$sender || $sender['points'] < $amount) throw new Exception('积分不足');
            $stmt = $db->prepare("SELECT id FROM users WHERE display_id = ? FOR UPDATE");
            $stmt->bind_param('s', $recipient_display_id);
            $stmt->execute();
            $recipient = $stmt->get_result()->fetch_assoc();
            if (!$recipient) throw new Exception('接收人ID不存在');
            $recipient_id = $recipient['id'];
            if ($sender_id === $recipient_id) throw new Exception('不能给自己赠送积分');
            $stmt = $db->prepare("UPDATE users SET points = points - ? WHERE id = ?");
            $stmt->bind_param('ii', $amount, $sender_id);
            $stmt->execute();
            $stmt = $db->prepare("UPDATE users SET points = points + ? WHERE id = ?");
            $stmt->bind_param('ii', $amount, $recipient_id);
            $stmt->execute();
            $db->commit();
            $_SESSION['points'] -= $amount;
            echo json_encode(['success' => true, 'message' => "成功赠送 {$amount} 积分"]);
        } catch (Exception $e) {
            $db->rollback();
            send_json_error(400, $e->getMessage());
        }
        break;

    case 'find_user':
        if ($request_method !== 'GET') {
            send_json_error(405, 'Method Not Allowed');
            break;
        }
        if (!isset($_SESSION['user_id'])) send_json_error(401, '请先登录');
        $phone = $_GET['phone'] ?? null;
        if (!$phone) send_json_error(400, '需要提供手机号');
        $stmt = $db->prepare("SELECT display_id FROM users WHERE phone_number = ?");
        $stmt->bind_param('s', $phone);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($user = $result->fetch_assoc()) {
            echo json_encode(['success' => true, 'user' => ['displayId' => $user['display_id']]]);
        } else {
            send_json_error(404, '未找到该用户');
        }
        break;
}
