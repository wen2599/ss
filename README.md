# Lottery Game

This is a full-stack lottery game application. It includes:

*   A **React frontend** for users to interact with the game.
*   A **PHP backend** that provides an API for the frontend.
*   A **Telegram bot** for game management and notifications.

The application allows users to register, log in, place bets, see lottery draw results, and manage their points.

## Backend Setup

### Prerequisites

*   PHP 7.4 or higher
*   Composer
*   SQLite 3

### Installation

1.  **Navigate to the backend directory:**
    ```bash
    cd backend
    ```

2.  **Install PHP dependencies:**
    ```bash
    composer install
    ```

### Database Setup

The application uses an SQLite database. The schema is located at `backend/api/schema.sql`.

1.  **Create the database file:**
    ```bash
    sqlite3 backend/api/database.db < backend/api/schema.sql
    ```
    This will create a `database.db` file in the `backend/api` directory with the required tables.

### Configuration

The backend requires a configuration file for the database path.

1.  **Create a `config.php` file** in `backend/api/` by copying the example file (if one existed, which it doesn't, so we create it):
2.  **Edit `backend/api/config.php`** and add the following content.
    ```php
    <?php
    define('DB_PATH', __DIR__ . '/database.db');
    // Add other configuration variables here if needed, e.g., Telegram bot token
    // define('TELEGRAM_BOT_TOKEN', 'YOUR_BOT_TOKEN');

    ```

### Running the Backend

You can run the backend using a local PHP server.

1.  **Start the PHP built-in server from the `backend/api` directory:**
    ```bash
    cd backend/api
    php -S localhost:8000
    ```
    The API will now be available at `http://localhost:8000`.

## Frontend Setup

### Prerequisites

*   Node.js (v16 or higher recommended)
*   npm

### Installation

1.  **Navigate to the frontend directory:**
    ```bash
    cd frontend
    ```

2.  **Install JavaScript dependencies:**
    ```bash
    npm install
    ```

### Running the Frontend

1.  **Start the development server:**
    ```bash
    npm start
    ```
    The application will open in your browser at `http://localhost:3000`.

2.  **Proxy to Backend:** The frontend is configured to proxy API requests to `http://localhost:8000`. Ensure your PHP backend server is running at that address.

### Building for Production

1.  **Create a production build:**
    ```bash
    npm run build
    ```
    This will create a `build` directory with the optimized, static assets. You can deploy this directory to any static web host, such as Cloudflare Pages.

## Telegram Bot Setup

The application includes a Telegram bot for processing lottery results and settling bets.

### Configuration

1.  **Get a Bot Token:** Talk to the [BotFather](https://t.me/botfather) on Telegram to create a new bot and get its token.

2.  **Set the Token:** Open `backend/api/config.php` and add or uncomment the following line, replacing `'YOUR_BOT_TOKEN'` with your actual token:
    ```php
    define('TELEGRAM_BOT_TOKEN', 'YOUR_BOT_TOKEN');
    ```

3.  **Permissions:** Make sure the bot is an administrator in the Telegram channel or group where it will operate, with permission to read messages.

### Running the Bot

The bot script (`backend/api/telegram_bot.php`) needs to be run periodically to check for new messages.

1.  **Manual Execution:**
    ```bash
    php backend/api/telegram_bot.php
    ```

2.  **Automated Execution (Cron Job):**
    For production, it is highly recommended to set up a cron job to run the script every minute.
    ```cron
    * * * * * /usr/bin/php /path/to/your/project/backend/api/telegram_bot.php >> /path/to/your/project/bot.log 2>&1
    ```
    *Remember to use absolute paths for your server.*
