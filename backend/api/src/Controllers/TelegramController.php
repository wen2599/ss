<?php
declare(strict_types=1);

namespace App\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use App\Models\ApiKey;
use App\Models\User;
use GuzzleHttp\Client;
use Telegram\Bot\Api;

class TelegramController
{
    public function webhook(Request $request, Response $response): Response
    {
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
        } elseif (strpos($text, '/deleteuser') === 0) {
            if ((string) $chatId !== $_ENV['TELEGRAM_ADMIN_ID']) {
                $telegram->sendMessage([
                    'chat_id' => $chatId,
                    'text' => 'You are not authorized to use this command.',
                ]);
                return $response;
            }

            $parts = explode(' ', $text);
            if (count($parts) < 2) {
                $telegram->sendMessage([
                    'chat_id' => $chatId,
                    'text' => 'Please provide a username or user ID to delete.',
                ]);
                return $response;
            }

            $identifier = $parts[1];
            $user = User::where('username', $identifier)->orWhere('id', $identifier)->first();

            if ($user) {
                $user->delete();
                $telegram->sendMessage([
                    'chat_id' => $chatId,
                    'text' => "User `" . $this->escapeMarkdownV2($identifier) . "` has been deleted.",
                    'parse_mode' => 'MarkdownV2',
                ]);
            } else {
                $telegram->sendMessage([
                    'chat_id' => $chatId,
                    'text' => "User `" . $this->escapeMarkdownV2($identifier) . "` not found.",
                    'parse_mode' => 'MarkdownV2',
                ]);
            }
        } elseif (strpos($text, '/set_gemini_api_key') === 0) {
            if ((string) $chatId !== $_ENV['TELEGRAM_ADMIN_ID']) {
                $telegram->sendMessage([
                    'chat_id' => $chatId,
                    'text' => 'You are not authorized to use this command.',
                ]);
                return $response;
            }

            $parts = explode(' ', $text);
            if (count($parts) < 2) {
                $telegram->sendMessage([
                    'chat_id' => $chatId,
                    'text' => 'Please provide the new Gemini API key.',
                ]);
                return $response;
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
            if ((string) $chatId !== $_ENV['TELEGRAM_ADMIN_ID']) {
                $telegram->sendMessage([
                    'chat_id' => $chatId,
                    'text' => 'You are not authorized to use this command.',
                ]);
                return $response;
            }

            $parts = explode(' ', $text, 2);
            if (count($parts) < 2) {
                $telegram->sendMessage([
                    'chat_id' => $chatId,
                    'text' => 'Please provide a prompt for the Cloudflare AI.',
                ]);
                return $response;
            }

            $prompt = $parts[1];

            $client = new Client();
            $response = $client->post(
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

            $body = json_decode((string) $response->getBody(), true);
            $aiResponse = $body['result']['response'];

            $telegram->sendMessage([
                'chat_id' => $chatId,
                'text' => $aiResponse,
            ]);
        }

        return $response;
    }

    private function escapeMarkdownV2($string)
    {
        $charsToEscape = ['_', '*', '[', ']', '(', ')', '~', '`', '>', '#', '+', '-', '=', '|', '{', '}', '.', '!'];
        foreach ($charsToEscape as $char) {
            $string = str_replace($char, '\\' . $char, $string);
        }
        return $string;
    }
}
