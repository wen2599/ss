<?php
require_once '../db.php';

function getLatest() {
    global $pdo;
    $stmt = $pdo->query("SELECT * FROM lottery_results ORDER BY draw_time DESC LIMIT 1");
    echo json_encode($stmt->fetch());
}

function getHistory() {
    global $pdo;
    $stmt = $pdo->query("SELECT * FROM lottery_results ORDER BY draw_time DESC");
    echo json_encode($stmt->fetchAll());
}