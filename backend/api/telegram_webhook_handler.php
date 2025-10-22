<?php
declare(strict_types=1);

// This script is a self-contained webhook handler for the Telegram bot.

require __DIR__ . '/vendor/autoload.php';

use App\Models\ApiKey;
use App\Models\User;
use GuzzleHttp\Client;
use Telegram\Bot\Api;
use Telegram\Bot\Keyboard\Keyboard;

// --- Helper Functions ---
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

    if ($text === '/start') {
        $keyboard = Keyboard::make(['keyboard' => [['/help']], 'resize_keyboard' => true, 'one_time_keyboard' => false]);
        $telegram->sendMessage([
            'chat_id' => $chatId,
            'text' => '你好！我是您的用户管理机器人。您可以通过以下命令进行操作：',
            'reply_markup' => $keyboard,
        ]);
    } elseif ($text === '/help') {
        $helpText = "可用管理员命令：\n\n";
        $helpText .= "/deleteuser <用户名|ID> - 删除用户。\n";
        $helpText .= "/set_gemini_api_key <API密钥> - 设置 Gemini API 密钥。\n";
        $helpText .= "/cfai <提示> - 发送提示给 Cloudflare AI。\n";

        $telegram->sendMessage([
            'chat_id' => $chatId,
            'text' => $helpText,
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
    }
} catch (\Exception $e) {
    // Log the error
    error_log($e->getMessage());
}
