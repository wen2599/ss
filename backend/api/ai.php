<?php
require_once '../db.php';
require_once '../functions.php';

function recognize($emailId = null) {
    global $pdo;

    if ($emailId === null) {
        // If emailId is not passed as an argument, try to get it from POST data
        $data = json_decode(file_get_contents('php://input'), true);
        $emailId = $data['email_id'];
    }

    if (!$emailId) {
        json_error('email_id is required');
    }

    $stmt = $pdo->prepare("SELECT body, template FROM emails WHERE id = ?");
    $stmt->execute([$emailId]);
    $row = $stmt->fetch();

    if (!$row) {
        json_error('Email not found', 404);
    }

    $body = $row['body'];
    $template = $row['template'] ?: "从邮件中提取多条六合彩下注单，每条: {user, numbers: array, special: int, amount: number}，输出 JSON 数组。邮件: ";

    $prompt = str_replace('{body}', $body, $template);
    try {
        $response = callGemini($prompt);  // 优先 Gemini
    } catch (Exception $e) {
        $response = callCloudflareAI($prompt)['response'];
    }
    $bets = json_decode($response, true);

    // 生成新模板如果无
    if (!$row['template']) {
        $templatePrompt = "基于这个识别结果: " . $response . "，生成通用 prompt 模板，含 {body}";
        $newTemplate = callGemini($templatePrompt);
        $pdo->prepare("UPDATE emails SET template = ? WHERE id = ?")->execute([$newTemplate, $emailId]);
    }

    $pdo->prepare("UPDATE emails SET bets_json = ? WHERE id = ?")->execute([json_encode($bets), $emailId]);

    // Only echo JSON if it's an API call, not an internal call
    if (isset($data)) {
        echo json_encode(['bets' => $bets]);
    }
}

function dialog() {
    global $pdo;
    $data = json_decode(file_get_contents('php://input'), true);
    $emailId = $data['email_id'];
    $message = $data['message'];
    $history = $data['history'] ?: [];  // array of {role, content}

    $stmt = $pdo->prepare("SELECT body, bets_json FROM emails WHERE id = ?");
    $stmt->execute([$emailId]);
    $row = $stmt->fetch();

    if (!$row) {
        json_error('Email not found', 404);
    }

    $prompt = "基于邮件: {$row['body']} 和当前表单: " . ($row['bets_json'] ? $row['bets_json'] : '[]') . "，响应用户: $message。输出: {\"response_text\": \"...\", \"corrected_json\": [...]}。历史: " . json_encode($history);

    try {
        $response = callGemini($prompt);
    } catch (Exception $e) {
        $response = callCloudflareAI($prompt)['response'];
    }

    $parsed = json_decode($response, true);

    // Ensure parsed has expected structure
    if (!isset($parsed['response_text']) || !isset($parsed['corrected_json'])) {
        error_log("AI Dialog Error: Unexpected response structure - " . $response);
        json_error("AI Dialog Error: Unexpected response from AI.", 500);
    }

    $history[] = ['role' => 'user', 'content' => $message];
    $history[] = ['role' => 'assistant', 'content' => $parsed['response_text']];

    $pdo->prepare("UPDATE emails SET dialog_history = ?, corrected_bets_json = ? WHERE id = ?")
        ->execute([json_encode($history), json_encode($parsed['corrected_json']), $emailId]);

    echo json_encode($parsed);
}