<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class TelegramController extends Controller
{
    /**
     * Handle incoming Telegram webhook updates.
     */
    public function webhook(Request $request)
    {
        // Validate the secret token from the header
        $secretToken = config('services.telegram.webhook_secret');
        if ($secretToken && $request->header('X-Telegram-Bot-Api-Secret-Token') !== $secretToken) {
            Log::warning('Invalid Telegram webhook secret token.');
            return response('Unauthorized', 403);
        }

        // Log the entire update for debugging
        Log::info('Telegram update received:', $request->all());

        // Acknowledge the request to Telegram
        return response()->json(['status' => 'ok']);
    }
}
