# Lottery Betting System (Refactored)

This project is a full-stack web application that allows users to place bets on lottery results. It features a complete user authentication system, automated parsing of bets from text input, and a fully automated system for fetching lottery results and settling bets via a Telegram bot.

This version has been significantly refactored for improved security, stability, and ease of deployment.

## Core Features

-   **User Authentication:** Secure user registration and login system.
-   **Text-Based Bet Submission:** Submit bets by uploading a plain text file.
-   **Automated Result Fetching & Settlement:** A Telegram bot monitors for results, parses them, and automatically settles all bets.
-   **Betting History:** View full history of bets and their settlement status.
-   **Email Integration:** Submit bets by sending an email to a configured address.

## Architecture

-   **Frontend:** A React single-page application built with Vite (`frontend/`).
-   **Backend:** A PHP application providing a RESTful API (`backend/`).
-   **Telegram Bot (Webhook):** A **new, unified** PHP script (`backend/api/tg_webhook.php`) that acts as a self-contained webhook for the Telegram bot.
-   **Cloudflare Worker (`frontend/public/_worker.js`):** A Cloudflare Worker script that can handle email forwarding. The frontend no longer relies on it for API proxying.

---

## Deployment Instructions

This project requires a three-part deployment: the database, the backend, and the frontend.

### Step 1: Set Up the Database

1.  **Create a database:** On your MySQL server, create a new, empty database.
2.  **Run migrations:** From the project root, run the migration script. This will create all necessary tables (`users`, `bets`, `lottery_draws`, `lottery_rules`).
    ```bash
    php backend/migrate.php
    ```
    *(Note: You will need to have PHP CLI installed on your local or build machine to run this command.)*
3.  **Alternative:** You can use the provided `backend/schema.sql` file to set up the database manually using a tool like phpMyAdmin or the command line:
    ```bash
    mysql -u your_user -p your_database < backend/schema.sql
    ```

### Step 2: Configure and Deploy the Backend & Telegram Bot

The backend now uses a `.env` file for all configuration, which is more secure and standard.

1.  **Create `.env` file:** In the `backend/` directory, copy the example file `backend/.env.example` to a new file named `backend/.env`.
    ```bash
    cp backend/.env.example backend/.env
    ```
2.  **Edit `.env` file:** Open `backend/.env` in a text editor and fill in your actual credentials.
    ```dotenv
    DB_HOST=127.0.0.1
    DB_PORT=3306
    DB_DATABASE=your_database_name
    DB_USERNAME=your_database_user
    DB_PASSWORD=your_database_password

    TELEGRAM_BOT_TOKEN=your_telegram_bot_token
    TELEGRAM_CHAT_ID=your_telegram_chat_id

    # The full URL of where your frontend is deployed
    FRONTEND_URL=https://ss.wenxiuxiu.eu.org
    ```
3.  **Deploy Backend:** Copy the entire `backend/` directory (including your new `.env` file) to your PHP-enabled web server.

4.  **Set Telegram Webhook:** You must register the **new unified webhook script** with Telegram. Open the following URL in your browser, replacing the placeholders.
    `https://api.telegram.org/bot<YOUR_BOT_TOKEN>/setWebhook?url=https://<YOUR_DOMAIN>/api/tg_webhook.php`

### Step 3: Configure and Deploy the Frontend

The frontend now also uses a `.env` file to know where the backend API is located.

1.  **Create `.env` file:** In the `frontend/` directory, create a new file named `.env`.
2.  **Add API URL:** Add the following line to `frontend/.env`, replacing the URL with the public domain of your backend.
    ```dotenv
    VITE_API_BASE_URL=https://wenge.cloudns.ch
    ```
3.  **Build and Deploy:**
    -   Navigate to the frontend directory: `cd frontend`
    -   Install dependencies: `npm install`
    -   Build the application: `npm run build`
    -   Upload the contents of the `frontend/dist` directory to your static hosting provider (e.g., Cloudflare Pages, Vercel, Netlify).

---
## Error Logging
If a critical error occurs, it will be logged to `backend/logs/error.log`. Check this file for detailed error messages.
