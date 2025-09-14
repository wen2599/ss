# Telegram Bot Instructions

This document provides instructions on how to configure and run the Telegram bot for managing the lottery game.

## 1. Configuration

Before running the bot, you must configure your bot token.

1.  **Get a Telegram Bot Token:** If you haven't already, create a new bot by talking to the [BotFather](https://t.me/botfather) on Telegram.
2.  **Add Token to Config:** Open the `backend/api/config.php` file. Find the following line and replace `'YOUR_BOT_TOKEN'` with your actual bot token:
    ```php
    define('TELEGRAM_BOT_TOKEN', 'YOUR_BOT_TOKEN');
    ```
3.  **Bot Permissions:** Make your bot an administrator of the Telegram channel or group where it will operate. It needs permission to read messages.

## 2. Running the Bot

The bot script is located at `backend/api/telegram_bot.php`. This script needs to be executed periodically to check for new messages (both winning number announcements and commands).

You can run the script manually from your server's command line:

```bash
php backend/api/telegram_bot.php
```

**For production use, it is highly recommended to set up a cron job to run this script automatically every minute.**

Example cron job configuration (edit with `crontab -e`):

```cron
* * * * * /usr/bin/php /path/to/your/project/backend/api/telegram_bot.php >> /path/to/your/project/bot.log 2>&1
```

*Remember to replace the paths with the correct absolute paths on your server.*

## 3. Bot Commands

The bot currently understands two types of messages: winning number announcements and settlement commands.

### Winning Number Announcements

The bot will automatically read messages in the channel. If a message matches one of the known formats for winning numbers, it will parse the result and update the corresponding draw in the database. You will receive a confirmation message in the channel.

### Settlement Command

To settle a draw, you must send a message **directly to the bot** or in the channel with the following format:

```
/settle <draw_id>
<paste the entire chat log here>
```

-   `/settle <draw_id>`: The command itself, followed by the draw number you wish to settle.
-   `<paste the entire chat log here>`: On new lines below the command, paste the full chat history that contains the user bets.

**Important:** The bot assumes each line in the chat log is in the format: `Username (1234): message text`, where `1234` is the user's unique **display_id**.

## 4. Supported Bet Formats (First Version)

In this initial version, the bot's settlement logic supports **Zodiac-based bets**.

**Example:**

`鸡狗猴各数50`

-   This will be parsed as a bet of 50 points on each number associated with the Rooster, Dog, and Monkey zodiacs.
-   The total cost will be automatically calculated and deducted from the user's points.

Other betting formats (Color Waves, Specific Numbers) can be added in the future.
