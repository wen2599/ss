# Bill & Lottery Tracker

This project provides a full-stack solution to automatically process bills from emails using AI and track lottery results. It consists of a React frontend deployed on Cloudflare Pages, a pure PHP backend deployed on Serv00, and a Cloudflare Worker for email processing.

## Project Architecture

*   **Frontend**: React application for user interface, deployed on Cloudflare Pages.
*   **Backend**: Pure PHP application for API endpoints, database interactions, and Telegram Bot logic, deployed on Serv00.
*   **Cloudflare Worker**: Intercepts incoming emails, parses them, and forwards the raw email content to the backend for AI processing.
*   **Database**: MySQL (or compatible) database to store user information, bills, sessions, and lottery results.

## Features

*   User registration and authentication (frontend and Telegram Bot).
*   View and manage parsed bills.
*   Track lottery results.
*   AI-powered email parsing for bill details and lottery numbers (using Gemini or Cloudflare AI).
*   Telegram Bot integration for user interaction and notifications.
*   Cron job for automated lottery result checking and winner notification.

## Setup and Deployment

### 1. Backend (Serv00)

#### A. Prerequisites

*   A Serv00 account with PHP and MySQL database access.
*   PHP 8.1+ with `pdo_mysql`, `curl`, `json` extensions enabled.
*   Your `.env` file with configurations (see below).

#### B. Database Setup

1.  **Create Database**: Log in to your Serv00 panel and create a new MySQL database.
2.  **Initialize Tables**: Upload `backend/database_schema.sql` to your Serv00 server. You can then import it using phpMyAdmin or run the `backend/initialize_database.php` script via SSH:
    ```bash
    php /path/to/your/backend/initialize_database.php
    ```
    *Make sure the `backend` directory is the root of your PHP deployment or configure your web server accordingly.*

#### C. Environment Variables (`backend/.env`)

Create a `.env` file in your `backend` directory on Serv00 with the following content. **Replace placeholder values with your actual credentials.**

```env
# --- Database Configuration ---
DB_HOST="mysql12.serv00.com" # Your Serv00 MySQL host
DB_PORT="3306"
DB_DATABASE="m10300_sj" # Your database name
DB_USER="m10300_yh"     # Your database user
DB_PASSWORD="Wenxiu1234*" # Your database password

# --- Security Tokens ---
# A strong, random string. Must match in Cloudflare Worker (WORKER_SECRET) and backend (EMAIL_HANDLER_SECRET).
EMAIL_HANDLER_SECRET="816429fb-1649-4e48-9288-7629893311a6"
# A strong, random string for Telegram Webhook secret_token.
TELEGRAM_WEBHOOK_SECRET="A7kZp9sR3bV2nC1mE6gH_jL5tP8vF4qW"
# A strong, random string for protecting admin endpoints.
ADMIN_SECRET="your_admin_secret_here_change_this"

# --- Telegram Bot Configuration ---
TELEGRAM_BOT_TOKEN="7222421940:AAEUTuFvonFCP1o-nRtNWbojCzSM9GQ--jU" # Your Telegram Bot API Token
TELEGRAM_ADMIN_ID="1878794912" # Your personal Telegram User ID for admin notifications
LOTTERY_CHANNEL_ID="-1002652392716" # Optional: Telegram Channel ID to announce lottery winners

# --- AI API Keys ---
GEMINI_API_KEY="AIzaSyDiG87DWQlcc4cSJqDno19ETfsXOTfgQDQ" # Your Google Gemini API Key
# DEEPSEEK_API_KEY="sk-xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx" # If using DeepSeek AI

# --- Cloudflare AI Configuration (if using Cloudflare AI) ---
CLOUDFLARE_ACCOUNT_ID="d67543ae14aee902dafcc4d251a713cd" # Your Cloudflare Account ID
CLOUDFLARE_API_TOKEN="boyDTR3dAZxbGawXSyG5k7R6fEclWaQVGBr8rgWw" # Your Cloudflare API Token (for AI Gateway)

# --- Backend Public URL ---
BACKEND_PUBLIC_URL="https://wenge.cloudns.ch" # The public URL of your backend
```

#### D. Deploy Backend Files

Upload all files and folders from the `backend` directory to the root directory of your PHP application on Serv00.

#### E. Set up Telegram Webhook

Once your backend is deployed and accessible via `https://wenge.cloudns.ch`, set up the Telegram webhook by visiting this URL in your browser (replace `your_admin_secret_here`):

`https://wenge.cloudns.ch/admin/set_telegram_webhook?secret=your_admin_secret_here`

You should see `{"ok":true,"result":true,"description":"Webhook was set"}` on success.

#### F. Configure Cron Job for Lottery Check

Set up a cron job on Serv00 to run `backend/check_lottery_data.php` periodically. For example, to run daily:

```bash
0 18 * * * php /path/to/your/backend/check_lottery_data.php
```
*(Adjust `/path/to/your/backend/` to your actual path on Serv00 and `0 18 * * *` to your desired schedule).* You can also trigger it manually via the admin endpoint: `https://wenge.cloudns.ch/admin/check_lottery_data?secret=your_admin_secret_here` (Note: `check_lottery_data` is a direct endpoint in this example, not under `/admin`).

### 2. Frontend (Cloudflare Pages)

#### A. Prerequisites

*   Node.js and npm installed locally.
*   A Cloudflare account with Cloudflare Pages set up.

#### B. Configuration

Edit `frontend/vite.config.js` to set the proxy target to your backend URL:

```javascript
// ...
export default defineConfig({
  plugins: [react()],
  server: {
    proxy: {
      '/api': {
        target: 'https://wenge.cloudns.ch', // Your Serv00 PHP backend URL
        changeOrigin: true,
        rewrite: (path) => path.replace(/^\/api/, ''),
        secure: true,
      },
    },
  },
})
```

#### C. Build and Deploy

1.  Navigate to the `frontend` directory in your local terminal:
    ```bash
    cd frontend
    ```
2.  Install dependencies:
    ```bash
    npm install
    ```
3.  Build the project for production:
    ```bash
    npm run build
    ```
4.  Deploy the contents of the `dist` folder to Cloudflare Pages. Configure Cloudflare Pages to build from the `frontend` directory and publish the `dist` directory.

### 3. Cloudflare Worker

#### A. Prerequisites

*   A Cloudflare account.
*   An email address configured with Cloudflare Email Routing.

#### B. Worker Script (`worker/worker.js`)

```javascript
// worker/worker.js

export default {
  async email(message, env, ctx) {
    const backendUrl = env.BACKEND_URL; // e.g., "https://wenge.cloudns.ch"
    const workerSecret = env.WORKER_SECRET; // Must match EMAIL_HANDLER_SECRET in backend .env

    if (!backendUrl || !workerSecret) {
      console.error("Missing BACKEND_URL or WORKER_SECRET environment variables.");
      return;
    }

    const rawEmail = await new Response(message.raw, { headers: { "Content-Type": "text/plain" } }).text();

    const emailData = {
      rawEmail: rawEmail,
      subject: message.headers.get("Subject") || "No Subject",
      recipient: message.to,
    };

    try {
      const response = await fetch(`${backendUrl}/email_webhook`, {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
          "X-Email-Worker-Secret": workerSecret, // Security header
        },
        body: JSON.stringify(emailData),
      });

      if (!response.ok) {
        const errorText = await response.text();
        console.error(`Failed to forward email to backend: ${response.status} - ${errorText}`);
      } else {
        console.log("Email successfully forwarded to backend.");
      }
    } catch (e) {
      console.error(`Error forwarding email to backend: ${e.message}`);
    }
  },
};
```

#### C. Deploy Worker

1.  Deploy the `worker/worker.js` script to Cloudflare Workers.
2.  **Environment Variables**: In your Cloudflare Worker settings, add the following environment variables:
    *   `BACKEND_URL`: Your backend's public URL (e.g., `https://wenge.cloudns.ch`).
    *   `WORKER_SECRET`: The same secret string you used for `EMAIL_HANDLER_SECRET` in your backend's `.env` file.
3.  **Email Routing**: Configure Cloudflare Email Routing to forward emails from a designated address (e.g., `bills@yourdomain.com`) to this Worker. The Worker will then process and forward them to your backend.

## Usage

### Frontend

Access your frontend application at `https://ss.wenxiuxiu.eu.org`.

*   **Register**: Create a new account.
*   **Login**: Log in to view your bills and lottery results.
*   **My Bills**: See a list of bills parsed from your emails.
*   **Lottery Results**: Check the latest lottery winning numbers.

### Telegram Bot

Interact with your Telegram Bot:

*   `/start`: Get a welcome message and command list.
*   `/register`: Create a new user account through a guided conversation.
*   `/login`: Link your Telegram chat to an existing web account.
*   `/bills`: View your latest bills.

### Email Processing

Forward your bill emails to the email address configured with your Cloudflare Email Routing (e.g., `bills@yourdomain.com`). The Cloudflare Worker will intercept, parse, and send them to your backend for AI processing.

## Development Notes

*   **Security**: Always use strong, random secrets and API keys. Do not expose sensitive information.
*   **Error Logging**: Ensure proper error logging is set up on your Serv00 PHP environment for debugging.
*   **AI Model**: You can switch between Gemini and Cloudflare AI in `backend/process_email_ai.php` by modifying the `ACTIVE_AI_SERVICE` define.

---

This `README.md` provides a comprehensive guide for setting up and deploying your project. Please review it carefully and ensure all configurations match your environment.