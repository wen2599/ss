# Lottery Betting System

This project is a full-stack web application that allows users to place bets on lottery results. It features a complete user authentication system, automated parsing of bets from text input, and a fully automated system for fetching lottery results and settling bets via a Telegram bot.

## Core Features

-   **User Authentication:** Secure user registration and login system. Only registered users can place bets.
-   **Text-Based Bet Submission:** Users can submit their bets by uploading a plain text file with a specific format. The system automatically parses this text into structured bet data.
-   **Automated Result Fetching & Settlement:** The system uses a Telegram bot to monitor a specific channel for lottery result announcements. When a new result is posted, the system automatically:
    1.  Parses the winning numbers from the message.
    2.  Stores the official result in the database.
    3.  Immediately settles all user bets placed for that lottery issue, calculating winnings.
-   **Betting History:** Users can view their full history of bets and see the status (unsettled/settled) and the results of each bet.
-   **Email Integration:** Users can also submit bets by sending an email with the bet text to a configured address. The system validates the sender's email and processes the bet automatically.

## Architecture

-   **Frontend:** A React single-page application built with Vite. It handles all user interface elements, including login/registration, bet submission, and viewing betting history and results. It is located in the `frontend/` directory.
-   **Backend:** A PHP application that provides a RESTful API for user management, bet parsing, and database interaction. It is located in the `backend/` directory.
-   **Telegram Bot (Webhook):** A PHP script (`backend/api/tg_webhook.php`) that acts as a webhook for a Telegram bot. It has two roles:
    1.  **Result Listener:** Listens to a specific Telegram channel for new lottery result messages, parses them, and stores them in the database.
    2.  **Bet Settler:** Triggers the bet settlement process (`settle_bets.php`) as soon as a new result is recorded.
    3.  **Admin Interface:** Provides basic administrative commands (e.g., listing users) to a super admin user.
-   **Cloudflare Worker (`worker/`):** A Cloudflare Worker script handles incoming emails, validates the sender, and forwards the bet content to the backend API.

## Bet Submission Format

The system's parser (`backend/api/parser.php`) understands specific text formats for bets. Bets can be combined in a single file, separated by spaces, commas, or semicolons.

-   **Special Number (特码):**
    -   Format: `特<Number>x<Amount>`
    -   Example: `特49x100` (Bets 100 on the number 49 as the special number)
-   **Zodiac Bet (生肖):**
    -   Format: `<ZodiacName(s)>各数<Amount>`
    -   Example: `鸡狗猴各数50` (Bets 50 on each number corresponding to the Zodiacs Chicken, Dog, and Monkey)
-   **Color Wave Bet (波色):**
    -   Format: `<ColorName>波各<Amount>`
    -   Example: `红波各10` (Bets 10 on each number in the Red wave)

## Deployment Instructions

This project requires a three-part deployment: the database, the backend, and the frontend.

### Step 1: Set Up the Database

1.  **Create a database:** On your MySQL server, create a new, empty database.
2.  **Run migrations:** From the project root, run the migration script. This will create all necessary tables (`users`, `bets`, `lottery_draws`, `lottery_rules`).
    ```bash
    php backend/migrate.php
    ```
    *(Note: You will need to have PHP CLI installed on your local or build machine to run this command.)*

### Step 2: Configure and Deploy the Backend & Telegram Bot

1.  **Configure Credentials:** Open the `backend/api/config.php` file in a text editor. You must replace the placeholder values (e.g., `'your_database_host'`) with your actual credentials for the database and Telegram bot.
    ```php
    // Example:
    define('DB_HOST', 'localhost');
    define('DB_NAME', 'my_lottery_db');
    // ... and so on for all credentials.
    ```
    **Security Warning:** Be very careful not to publicly expose this file with your completed credentials.

2.  **Deploy Backend:** Copy the entire `backend/` directory (with your modified `config.php`) to your PHP-enabled web server.

3.  **Set Telegram Webhook:** You must register the `tg_webhook.php` script with Telegram. Open the following URL in your browser, replacing the placeholders with your bot token and the public URL to your script. You only need to do this once.
    `https://api.telegram.org/bot<YOUR_BOT_TOKEN>/setWebhook?url=https://<YOUR_DOMAIN>/api/tg_webhook.php`

### Step 3: Deploy the Frontend

The frontend is a Vite application and can be deployed to any modern static hosting provider like Cloudflare Pages, Vercel, or Netlify.

1.  **Navigate to the frontend directory:** `cd frontend`
2.  **Install dependencies:** `npm install`
3.  **Build the application:** `npm run build`
4.  **Deploy:** Upload the contents of the `frontend/dist` directory to your hosting provider.
