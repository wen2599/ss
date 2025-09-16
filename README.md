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

## Deployment Instructions

This project requires a three-part deployment: the database, the backend, and the integrated frontend/worker.

### Step 1: Set Up the Database

1.  On your MySQL server, create a new database.
2.  Use the `backend/api/schema.sql` file to create the initial `chat_logs` table.
3.  Use the `backend/api/schema_v2.sql` file to create the `users` table and update the `chat_logs` table for user association.

### Step 2: Deploy the Backend

1.  Copy the entire contents of the `backend/` directory to your PHP-enabled web server (e.g., the one at `https://wenge.cloudns.ch`).
2.  Ensure the server path is configured so the API is accessible at `/api/` (e.g., `https://wenge.cloudns.ch/api/api.php`). This typically means placing the contents of `backend/api/` into your server's `/api/` directory.
3.  Update the database credentials in `api/config.php`.
4.  **Important:** The backend `api.php` script expects a secret key from the email worker. You must choose a strong, random secret and replace the placeholder `'A_VERY_SECRET_KEY'` in both `api.php` and `frontend/functions/_worker.js`.

### Step 3: Deploy the Frontend and Worker

This is done seamlessly using Cloudflare Pages.

1.  **Push to Git:** Ensure all the latest code, including `frontend/functions/_worker.js`, is in your Git repository.
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
