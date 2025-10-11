<?php
// This script acts as a simple wrapper to include the actual webhook handler.
// It exists to match the incorrect URL configured in the Telegram webhook settings.
require_once __DIR__ . '/../src/api/telegramWebhook.php';