<?php
declare(strict_types=1);

// This script is a self-contained webhook handler for the Telegram bot.

require __DIR__ . '/vendor/autoload.php';

use App\Models\ApiKey;
use App\Models\User;
use App\Models\Email;
use App\Models\LotteryNumber;
use GuzzleHttp\Client;
use Telegram\Bot\Api;
use Telegram\Bot\Keyboard\Keyboard;

// --- Helper Functions ---

/**
 * A general-purpose function to make API calls using cURL.
 *
 * @param string $url The URL for the API endpoint.
 * @param array $payload The data to be sent in the request body (will be JSON-encoded).
 * @param array $headers An array of HTTP headers.
 * @param string $method The HTTP method (e.g., 'POST', 'GET').
 * @return array An array containing the HTTP status code, response body, and any cURL error.
 */
function _call_api_curl(string $url, array $payload = [], array $headers = [], string $method = 'POST'): array
{
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);

    if (!empty($payload)) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    }

    // Add timeout options to prevent long waits
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10); // 10 seconds to connect
    curl_setopt($ch, CURLOPT_TIMEOUT, 30); // 30 seconds for the entire request

    $responseBody = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);

    curl_close($ch);

    return [
        'http_code' => $httpCode,
        'response_body' => $responseBody,
        'curl_error' => $curlError
    ];
}


/**
 * Calls the Google Gemini API.
 *
 * @param string $prompt The text prompt to send to Gemini.
 * @return string The text response from Gemini or an error message.
 */
function call_gemini_api($prompt) {
    $apiKeyRecord = ApiKey::where('service_name', 'gemini')->first();
    $apiKey = $apiKeyRecord->api_key ?? null;

    if (empty($apiKey) || $apiKey === 'your_gemini_api_key_here') {
        return '❌ **错误**: Gemini API 密钥未在数据库中配置。请使用 /set_gemini_api_key 命令进行设置。';
    }

    $apiUrl = "https://generativelanguage.googleapis.com/v1/models/gemini-2.5-flash:generateContent?key={$apiKey}";

    $payload = [
        'contents' => [
            ['parts' => [['text' => $prompt]]]
        ],
        'safetySettings' => [
            ['category' => 'HARM_CATEGORY_HARASSMENT', 'threshold' => 'BLOCK_NONE'],
            ['category' => 'HARM_CATEGORY_HATE_SPEECH', 'threshold' => 'BLOCK_NONE'],
            ['category' => 'HARM_CATEGORY_SEXUALLY_EXPLICIT', 'threshold' => 'BLOCK_NONE'],
            ['category' => 'HARM_CATEGORY_DANGEROUS_CONTENT', 'threshold' => 'BLOCK_NONE'],
        ],
    ];

    $headers = ['Content-Type: application/json'];

    // Use the general-purpose function to make the request
    $result = _call_api_curl($apiUrl, $payload, $headers);

    // Error handling and response parsing specific to Gemini API
    if ($result['http_code'] !== 200) {
        $responseData = json_decode($result['response_body'], true);
        $errorMessage = $responseData['error']['message'] ?? '未知错误';

        if (strpos($errorMessage, 'Insufficient Balance') !== false || $result['http_code'] === 402) {
            return "❌ **API 请求失败**: 账户余额不足。请检查您的 Gemini 账户并充值。";
        }
        return "❌ **API 请求失败**:\n状态码: {$result['http_code']}\n错误: {$errorMessage}\nCURL 错误: {$result['curl_error']}";
    }

    $responseData = json_decode($result['response_body'], true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        return '❌ **错误**: 解析 Gemini API 的 JSON 响应失败。';
    }

    $textResponse = $responseData['candidates'][0]['content']['parts'][0]['text'] ?? null;
    if (!$textResponse) {
        // Log the full response for debugging
        error_log('Gemini API did not return a valid text response. Full response: ' . $result['response_body']);
        return '❌ **错误**: 未在 Gemini API 输出中找到有效的文本响应。可能由于内容安全策略被拦截。';
    }

    return $textResponse;
}

function isAdmin(int $chatId): bool
{
    return (string) $chatId === $_ENV['TELEGRAM_ADMIN_ID'];
}

function escapeMarkdownV2(string $string): string
{
    $charsToEscape = ['_', '*', '[', ']', '(', ')', '~', '`', '>', '#', '+', '-', '=', '|', '{', '}', '.', '!'];
    foreach ($charsToEscape as $char) {
        $string = str_replace($char, '\\' . $char, $string);
    }
    return $string;
}

// --- Main Execution ---
try {
    // Load environment variables from the parent directory
    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../');
    $dotenv->load();

    // Initialize the Telegram API
    $telegram = new Api($_ENV['TELEGRAM_BOT_TOKEN']);
    $update = $telegram->getWebhookUpdate();

    $message = $update->getMessage();
    $chatId = $message->getChat()->getId();
    $text = $message->getText();

    $channelPost = $update->getChannelPost();
    $lotteryChannelId = $_ENV['LOTTERY_CHANNEL_ID'] ?? null;

    // Handle channel posts for lottery numbers
    if ($channelPost && $lotteryChannelId && (string) $channelPost->getChat()->getId() === $lotteryChannelId) {
        $postText = $channelPost->getText();
        // Example: "今天的开奖号码是：1, 2, 3, 4, 5, 6"
        if (preg_match('/(?:开奖号码是|号码：)[\s:]*([\d,\s]+)/u', $postText, $matches)) {
            $numbersString = trim($matches[1]);
            $numbersArray = array_map('intval', explode(',', $numbersString));

            LotteryNumber::create([
                'numbers' => $numbersArray,
                'draw_time' => now(), // Using Laravel's now() helper for current timestamp
            ]);

            // Optionally send a confirmation to the admin
            if (isAdmin((int) $_ENV['TELEGRAM_ADMIN_ID'])) {
                $telegram->sendMessage([
                    'chat_id' => $_ENV['TELEGRAM_ADMIN_ID'],
                    'text' => '✅ 已从频道保存彩票开奖号码：' . implode(', ', $numbersArray),
                ]);
            }
            exit; // Stop further processing for channel posts
        }
    }

    // Handle direct messages to the bot
    if ($text === '/start') {
        $keyboard = Keyboard::make(['keyboard' => [['/help', '/list_emails'], ['/latest_lottery']], 'resize_keyboard' => true, 'one_time_keyboard' => false]);
        $telegram->sendMessage([
            'chat_id' => $chatId,
            'text' => '你好！我是您的用户管理机器人。您可以通过以下命令进行操作：',
            'reply_markup' => $keyboard,
        ]);
    } elseif ($text === '/help') {
        $helpText = "可用管理员命令：\n\n";
        $helpText .= "/list_emails - 列出最近收到的邮件。\n";
        $helpText .= "/get_email <邮件ID> - 获取指定邮件的详细信息。\n";
        $helpText .= "/latest_lottery - 获取最新的彩票开奖号码。\n";
        $helpText .= "/deleteuser <用户名|ID> - 删除用户。\n";
        $helpText .= "/set_gemini_api_key <API密钥> - 设置 Gemini API 密钥。\n";
        $helpText .= "/gemini <提示> - 发送提示给 Google Gemini。\n";
        $helpText .= "/cfai <提示> - 发送提示给 Cloudflare AI。\n";

        $telegram->sendMessage([
            'chat_id' => $chatId,
            'text' => $helpText,
        ]);
    } elseif ($text === '/list_emails') {
        if (!isAdmin($chatId)) {
            $telegram->sendMessage([
                'chat_id' => $chatId,
                'text' => '您无权使用此命令。',
            ]);
            exit;
        }

        $emails = Email::orderByDesc('received_at')->take(5)->get();

        if ($emails->isEmpty()) {
            $telegram->sendMessage([
                'chat_id' => $chatId,
                'text' => '目前没有收到任何邮件。',
            ]);
            exit;
        }

        $response = "最近收到的邮件：\n\n";
        foreach ($emails as $email) {
            $response .= sprintf(
                "*ID:* %s\n*发件人:* %s\n*主题:* %s\n*时间:* %s\n\n",
                escapeMarkdownV2((string)$email->id),
                escapeMarkdownV2($email->sender),
                escapeMarkdownV2($email->subject ?? '无主题'),
                escapeMarkdownV2($email->received_at->format('Y-m-d H:i:s'))
            );
        }

        $telegram->sendMessage([
            'chat_id' => $chatId,
            'text' => $response,
            'parse_mode' => 'MarkdownV2',
        ]);
    } elseif (strpos($text, '/get_email') === 0) {
        if (!isAdmin($chatId)) {
            $telegram->sendMessage([
                'chat_id' => $chatId,
                'text' => '您无权使用此命令。',
            ]);
            exit;
        }

        $parts = explode(' ', $text);
        if (count($parts) < 2) {
            $telegram->sendMessage([
                'chat_id' => $chatId,
                'text' => '请提供要获取的邮件ID。',
            ]);
            exit;
        }

        $emailId = (int) $parts[1];
        $email = Email::find($emailId);

        if (!$email) {
            $telegram->sendMessage([
                'chat_id' => $chatId,
                'text' => sprintf('未找到ID为 `%s` 的邮件。', escapeMarkdownV2((string)$emailId)),
                'parse_mode' => 'MarkdownV2',
            ]);
            exit;
        }

        $response = sprintf(
            "*邮件ID:* %s\n*发件人:* %s\n*主题:* %s\n*接收时间:* %s\n\n",
            escapeMarkdownV2((string)$email->id),
            escapeMarkdownV2($email->sender),
            escapeMarkdownV2($email->subject ?? '无主题'),
            escapeMarkdownV2($email->received_at->format('Y-m-d H:i:s'))
        );

        if ($email->ai_parsed_json) {
            $response .= "*AI解析数据：*\n";
            $response .= "```json\n" . escapeMarkdownV2(json_encode(json_decode($email->ai_parsed_json), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) . "\n```\n";
        }

        $response .= "*原始内容：*\n";
        $response .= "```\n" . escapeMarkdownV2($email->raw_content) . "\n```";

        $telegram->sendMessage([
            'chat_id' => $chatId,
            'text' => $response,
            'parse_mode' => 'MarkdownV2',
        ]);
    } elseif ($text === '/latest_lottery') {
        if (!isAdmin($chatId)) {
            $telegram->sendMessage([
                'chat_id' => $chatId,
                'text' => '您无权使用此命令。',
            ]);
            exit;
        }

        $latestLottery = LotteryNumber::orderByDesc('draw_time')->first();

        if (!$latestLottery) {
            $telegram->sendMessage([
                'chat_id' => $chatId,
                'text' => '目前还没有彩票开奖号码记录。',
            ]);
            exit;
        }

        $response = sprintf(
            "*最新开奖号码：* %s\n*开奖时间:* %s",
            escapeMarkdownV2(implode(', ', $latestLottery->numbers)),
            escapeMarkdownV2($latestLottery->draw_time->format('Y-m-d H:i:s'))
        );

        $telegram->sendMessage([
            'chat_id' => $chatId,
            'text' => $response,
            'parse_mode' => 'MarkdownV2',
        ]);
    } elseif (strpos($text, '/deleteuser') === 0) {
        if (!isAdmin($chatId)) {
            $telegram->sendMessage([
                'chat_id' => $chatId,
                'text' => '您无权使用此命令。',
            ]);
            exit;
        }

        $parts = explode(' ', $text);
        if (count($parts) < 2) {
            $telegram->sendMessage([
                'chat_id' => $chatId,
                'text' => '请提供要删除的用户名或用户ID。',
            ]);
            exit;
        }

        $identifier = $parts[1];
        $user = User::where('username', $identifier)->orWhere('id', $identifier)->first();

        if ($user) {
            $user->delete();
            $telegram->sendMessage([
                'chat_id' => $chatId,
                'text' => "用户 `" . escapeMarkdownV2($identifier) . "` 已被删除。",
                'parse_mode' => 'MarkdownV2',
            ]);
        } else {
            $telegram->sendMessage([
                'chat_id' => $chatId,
                'text' => "用户 `" . escapeMarkdownV2($identifier) . "` 未找到。",
                'parse_mode' => 'MarkdownV2',
            ]);
        }
    } elseif (strpos($text, '/set_gemini_api_key') === 0) {
        if (!isAdmin($chatId)) {
            $telegram->sendMessage([
                'chat_id' => $chatId,
                'text' => '您无权使用此命令。',
            ]);
            exit;
        }

        $parts = explode(' ', $text);
        if (count($parts) < 2) {
            $telegram->sendMessage([
                'chat_id' => $chatId,
                'text' => '请提供新的 Gemini API 密钥。',
            ]);
            exit;
        }

        $newApiKey = $parts[1];

        ApiKey::updateOrCreate(
            ['service_name' => 'gemini'],
            ['api_key' => $newApiKey]
        );

        $telegram->sendMessage([
            'chat_id' => $chatId,
            'text' => 'Gemini API 密钥已更新。',
        ]);
    } elseif (strpos($text, '/cfai') === 0) {
        if (!isAdmin($chatId)) {
            $telegram->sendMessage([
                'chat_id' => $chatId,
                'text' => '您无权使用此命令。',
            ]);
            exit;
        }

        $parts = explode(' ', $text, 2);
        if (count($parts) < 2) {
            $telegram->sendMessage([
                'chat_id' => $chatId,
                'text' => '请提供 Cloudflare AI 的提示。',
            ]);
            exit;
        }

        $prompt = $parts[1];

        $client = new Client();
        $apiResponse = $client->post(
            "https://api.cloudflare.com/client/v4/accounts/{$_ENV['CLOUDFLARE_ACCOUNT_ID']}/ai/run/@cf/meta/llama-2-7b-chat-int8",
            [
                'headers' => [
                    'Authorization' => "Bearer {$_ENV['CLOUDFLARE_API_TOKEN']}",
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'prompt' => $prompt,
                ],
            ]
        );

        $body = json_decode((string) $apiResponse->getBody(), true);
        $aiResponse = $body['result']['response'];

        $telegram->sendMessage([
            'chat_id' => $chatId,
            'text' => $aiResponse,
        ]);
    } elseif (strpos($text, '/gemini') === 0) {
        if (!isAdmin($chatId)) {
            $telegram->sendMessage([
                'chat_id' => $chatId,
                'text' => '您无权使用此命令。',
            ]);
            exit;
        }

        $parts = explode(' ', $text, 2);
        if (count($parts) < 2 || empty(trim($parts[1]))) {
            $telegram->sendMessage([
                'chat_id' => $chatId,
                'text' => '请提供要发送给 Gemini 的提示。用法: /gemini <您的文本>',
            ]);
            exit;
        }

        $prompt = trim($parts[1]);

        // Let the user know the bot is thinking
        $telegram->sendMessage([
            'chat_id' => $chatId,
            'text' => '🤔 正在思考中，请稍候...',
        ]);

        $geminiResponse = call_gemini_api($prompt);

        // Edit the "thinking" message with the final response
        $telegram->sendMessage([
            'chat_id' => $chatId,
            'text' => escapeMarkdownV2($geminiResponse),
            'parse_mode' => 'MarkdownV2',
        ]);
    }
} catch (\Exception $e) {
    // Log the error
    error_log($e->getMessage());
}
