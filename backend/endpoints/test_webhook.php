<?php
// A simple webhook for testing purposes.

// Get the raw POST data from Telegram
$update_json = file_get_contents('php://input');

// Decode the update
$update = json_decode($update_json, true);

// If we got a message with a chat ID, send a reply
if (isset($update['message']['chat']['id'])) {
    $chat_id = $update['message']['chat']['id'];
    $bot_token = '7222421940:AAEUTuFvonFCP1o-nRtNWbojCzSM9GQ--jU'; // Hardcoded bot token
    $url = 'https://api.telegram.org/bot' . $bot_token . '/sendMessage';
    
    $payload = [
        'chat_id' => $chat_id,
        'text' => '✅ It works! The server can run PHP and send messages. The problem is in the main bot script.',
    ];
    
    $options = [
        'http' => [
            'header'  => "Content-type: application/json\r\n",
            'method'  => 'POST',
            'content' => json_encode($payload),
        ],
    ];
    
    // Send the request
    file_get_contents($url, false, stream_context_create($options));
}
?>