<?php
declare(strict_types=1);

namespace App\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use App\Models\User;
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
