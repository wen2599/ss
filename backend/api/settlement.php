<?php
require_once '../db.php';
require_once '../functions.php';

function calculate() {
    global $pdo;
    $data = json_decode(file_get_contents('php://input'), true);
    $emailId = $data['email_id'];
    $stmt = $pdo->prepare("SELECT corrected_bets_json AS bets_json, bets_json AS fallback FROM emails WHERE id = ?");
    $stmt->execute([$emailId]);
    $bets = json_decode($stmt->fetch()['bets_json'] ?: $fallback, true);

    // 获取最新开奖
    $resultStmt = $pdo->query("SELECT numbers, special FROM lottery_results ORDER BY draw_time DESC LIMIT 1");
    $result = $resultStmt->fetch();

    $settlement = calculateSettlement($bets, $result);
    $pdo->prepare("UPDATE emails SET settlement_json = ? WHERE id = ?")->execute([json_encode($settlement), $emailId]);
    echo json_encode(['settlement' => $settlement]);
}