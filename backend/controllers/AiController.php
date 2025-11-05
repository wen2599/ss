<?php
class AiController {
    public function parseEmailWithCloudflareAI($content) {
        $accountId = getenv('CLOUDFLARE_ACCOUNT_ID');
        $apiToken = getenv('CLOUDFLARE_AI_TOKEN');
        $url = "https://api.cloudflare.com/client/v4/accounts/{$accountId}/ai/run/@cf/meta/llama-2-7b-chat-int8";

        $prompt = "你是一个六合彩注单格式化工具。请从以下文本中提取期号、所有下注的号码和金额，并以严格的JSON格式返回。JSON结构为: {\"issue_number\": \"期号\", \"bets\": [{\"type\": \"玩法\", \"numbers\": \"号码\", \"amount\": 金额}]}. 如果无法识别，返回 {\"error\": \"无法识别\"}。文本内容：\n\n" . $content;
        
        $data = ['prompt' => $prompt];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $apiToken,
            'Content-Type: application/json'
        ]);

        $response = curl_exec($ch);
        curl_close($ch);

        return json_decode($response, true);
    }
    
    // Gemini 的调用函数可以类似地实现
}
