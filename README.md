# Chat Log Form Generator & Email Processor

This project is a full-stack web application that allows registered users to parse chat logs into a structured form. It features a complete user authentication system and supports two methods for processing chat logs: direct file upload and automated email processing.

## Core Features

-   **User Authentication:** Secure user registration and login system. Only registered users can access the application.
-   **File Upload:** Logged-in users can upload a chat log file (e.g., from WhatsApp) to have it parsed and displayed.
-   **Email Processing:** The system can be configured with a domain email address. It will only process emails sent from registered user emails, automatically parsing the chat log within and adding it to that user's account.
-   **Data Storage:** All parsed chat logs are associated with a user account and stored in a MySQL database.

## Architecture

-   **Frontend:** A React single-page application built with Vite. It handles all user interface elements, including login/registration forms, file uploads, and displaying stored logs. It is located in the `frontend/` directory.
-   **Backend:** A PHP application that provides a RESTful API for user management, chat log parsing, and database interaction. It is located in the `backend/` directory.
-   **Cloudflare Worker (`_worker.js`):** A single, unified worker that is deployed with the frontend on Cloudflare Pages. It has two roles:
    1.  **API Proxy:** It intercepts all requests from the frontend to `/api/*` and securely forwards them to the PHP backend. This solves all CORS issues for both web and native app (APK) builds.
    2.  **Email Handler:** It is configured to receive emails, validates that the sender is a registered user by calling a backend verification endpoint, and then forwards the email content to the backend's upload API to be parsed and stored.

## Telegram Bot Administration

The application includes a Telegram bot with administrative features. These features are restricted to the Super Admin user defined by the `TELEGRAM_SUPER_ADMIN_ID` in `config.php`.

### Setting Up the Webhook

To receive commands, you must register the `tg_webhook.php` script with Telegram. You only need to do this once. Open the following URL in your browser, replacing `<YOUR_BOT_TOKEN>` and `<YOUR_WEBHOOK_URL>` with your actual values:

`https://api.telegram.org/bot<YOUR_BOT_TOKEN>/setWebhook?url=<YOUR_WEBHOOK_URL>`

For example:
`https://api.telegram.org/bot7279950407:AAGo/setWebhook?url=https://wenge.cloudns.ch/api/tg_webhook.php`

You should see a success message from Telegram.

### Available Commands

-   `/start`
    -   Displays a welcome message and lists available commands.
-   `/listusers`
    -   Lists all registered users in the database with their ID, email, and creation date.
-   `/deleteuser <email>`
    -   Deletes a user from the database based on their email address.
    -   **Usage:** `/deleteuser user@example.com`

## Deployment Instructions

This project requires a three-part deployment: the database, the backend, and the integrated frontend/worker.

### Step 1: Set Up the Database

1.  **Create an empty database:** On your MySQL server, create a new, empty database for the application.
2.  **Configure credentials:** In the `backend/api/` directory, copy the `.env.example` file to `.env` and fill in the connection details for the database you just created.
3.  **Run the migration script:** From your terminal, at the root of the project, run the following command:
    ```bash
    php backend/migrate.php
    ```
    This script will automatically create all the necessary tables (`users`, `chat_logs`, etc.) in your database. If you add new migration files in the future, you can simply run this command again to apply the new changes.

### Step 2: Configure and Deploy the Backend

1.  **Configure Environment Variables:** The backend now uses a `.env` file to handle sensitive configurations like database credentials.
    *   In the `backend/api/` directory, you will find a file named `.env.example`.
    *   Create a copy of this file and rename it to `.env`.
    *   Open the `.env` file and fill in your database connection details (`DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS`).
    *   **Security Note:** The `.env` file should never be committed to your Git repository. It is included in `.gitignore` to prevent this.

2.  **Deploy the Backend Files:**
    *   Copy the entire contents of the `backend/` directory (including your new `.env` file) to your PHP-enabled web server (e.g., `https://wenge.cloudns.ch`).
    *   Ensure the server path is configured so the API is accessible at `/api/` (e.g., `https://wenge.cloudns.ch/api/api.php`). This typically means placing the contents of `backend/api/` into your server's `/api/` directory.

3.  **Configure the Worker Secret:**
    *   The backend `api.php` script expects a secret key from the email worker. You must choose a strong, random secret and replace the placeholder `'A_VERY_SECRET_KEY'` in both `api.php` and `frontend/public/_worker.js`.

4.  **Production Environment:**
    *   For production, it is highly recommended to set environment variables directly on your server instead of using a `.env` file. This is more secure. The method for setting server-level environment variables depends on your hosting provider (e.g., using cPanel, Plesk, or command-line exports).

### Step 3: Deploy the Frontend and Worker

This is done seamlessly using Cloudflare Pages.

1.  **Push to Git:** Ensure all the latest code, including `frontend/public/_worker.js`, is in your Git repository.
2.  **Create Pages Project:** In Cloudflare, create a new Pages project and connect it to your Git repository.
3.  **Configure Build:**
    *   **Framework preset:** `Vite`
    *   **Build command:** `npm run build`
    *   **Build output directory:** `build`
    *   **Root directory:** `frontend`
4.  **Deploy:** Save and Deploy. Cloudflare will build and deploy your site and worker.

### Step 4: Configure Email Routing

1.  In your Cloudflare dashboard, go to **Email** > **Email Routing**.
2.  Follow the steps to set up your domain's MX records.
3.  Create a new route:
    *   **Custom address:** Enter the email username (e.g., `chat`).
    *   **Action:** Select **Send to a Worker**.
    *   **Worker:** Select the Pages project you just deployed.
4.  Click **Save**.

Your application is now fully deployed and functional.
