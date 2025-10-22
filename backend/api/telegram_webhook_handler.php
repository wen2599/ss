<?php
declare(strict_types=1);

// This script is a self-contained webhook handler for the Telegram bot.

require __DIR__ . '/vendor/autoload.php';

use App\Models\ApiKey;
use App\Models\User;
use GuzzleHttp\Client;
use Telegram\Bot\Api;

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
        $telegram->sendMessage([
            'chat_id' => $chatId,
            'text' => 'Hello! I am your user management bot.',
        ]);
    } elseif ($text === '/help') {
        $helpText = "Available admin commands:\n\n";
        $helpText .= "/deleteuser <username|id> - Deletes a user.\n";
        $helpText .= "/set_gemini_api_key <api_key> - Sets the Gemini API key.\n";
        $helpText .= "/cfai <prompt> - Sends a prompt to the Cloudflare AI.\n";

        $telegram->sendMessage([
            'chat_id' => $chatId,
            'text' => $helpText,
        ]);
    } elseif (strpos($text, '/deleteuser') === 0) {
        if (!isAdmin($chatId)) {
            $telegram->sendMessage([
                'chat_id' => $chatId,
                'text' => 'You are not authorized to use this command.',
            ]);
            exit;
        }

        $parts = explode(' ', $text);
        if (count($parts) < 2) {
            $telegram->sendMessage([
                'chat_id' => $chatId,
                'text' => 'Please provide a username or user ID to delete.',
            ]);
            exit;
        }

        $identifier = $parts[1];
        $user = User::where('username', $identifier)->orWhere('id', $identifier)->first();

        if ($user) {
            $user->delete();
            $telegram->sendMessage([
                'chat_id' => $chatId,
                'text' => "User `" . escapeMarkdownV2($identifier) . "` has been deleted.",
                'parse_mode' => 'MarkdownV2',
            ]);
        } else {
            $telegram->sendMessage([
                'chat_id' => $chatId,
                'text' => "User `" . escapeMarkdownV2($identifier) . "` not found.",
                'parse_mode' => 'MarkdownV2',
            ]);
        }
    } elseif (strpos($text, '/set_gemini_api_key') === 0) {
        if (!isAdmin($chatId)) {
            $telegram->sendMessage([
                'chat_id' => $chatId,
                'text' => 'You are not authorized to use this command.',
            ]);
            exit;
        }

        $parts = explode(' ', $text);
        if (count($parts) < 2) {
            $telegram->sendMessage([
                'chat_id' => $chatId,
                'text' => 'Please provide the new Gemini API key.',
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
            'text' => 'Gemini API key has been updated.',
        ]);
    } elseif (strpos($text, '/cfai') === 0) {
        if (!isAdmin($chatId)) {
            $telegram->sendMessage([
                'chat_id' => $chatId,
                'text' => 'You are not authorized to use this command.',
            ]);
            exit;
        }

        $parts = explode(' ', $text, 2);
        if (count($parts) < 2) {
            $telegram->sendMessage([
                'chat_id' => $chatId,
                'text' => 'Please provide a prompt for the Cloudflare AI.',
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
