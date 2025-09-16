# Chat Log Form Generator & Email Processor

This project is a web application that allows users to parse chat logs into a structured form. It has two main functionalities:
1.  **File Upload:** Users can upload a chat log file (e.g., from WhatsApp) to have it parsed and displayed in a table.
2.  **Email Processing:** Users can email chat logs to a configured domain email address. The system will automatically process the email, parse the chat log, and store it in a database.

The application is composed of three main parts: a PHP backend, a React frontend, and a Cloudflare Worker for proxying and email processing.

## Architecture Overview

-   **Frontend:** A React application built with Vite. It allows users to upload files and view stored chat logs. It is located in the `frontend/` directory.
-   **Backend:** A PHP application that handles chat log parsing and database interaction. It is located in the `backend/` directory.
-   **Cloudflare Worker (`_worker.js`):** A single, unified worker that is deployed with the frontend on Cloudflare Pages. It has two roles:
    1.  **API Proxy:** It intercepts all requests from the frontend to `/api/*` and securely forwards them to the PHP backend. This solves all CORS issues for both web and native app (APK) builds.
    2.  **Email Handler:** It is configured to receive emails, extract the chat log from the email body, and send it to the backend's upload API to be parsed and stored.

## Deployment Instructions

This project requires a three-part deployment: the backend, the database, and the frontend with the integrated worker.

### Step 1: Deploy the Backend

1.  Copy the entire contents of the `backend/` directory to your PHP-enabled web server (e.g., the one at `https://wenge.cloudns.ch`).
2.  Ensure the server path is configured correctly. Based on your input, the final API endpoints should be accessible at `https://wenge.cloudns.ch/api/api.php` and `https://wenge.cloudns.ch/api/get_logs.php`. This means you should likely place the contents of the `backend/api` directory into the `/api` directory on your web server's root.
3.  Ensure the `uploads/` directory inside your API directory is writable by the web server.
4.  Update the database credentials in `api/config.php` to match your database server.

### Step 2: Set Up the Database

1.  On your MySQL database server, create a new database (e.g., `m1030`).
2.  Use the `backend/api/schema.sql` file to create the necessary `chat_logs` table. You can do this by importing the `.sql` file using a tool like phpMyAdmin or by running the SQL command directly on your database.

### Step 3: Deploy the Frontend and Worker with Cloudflare Pages

The frontend and the worker are designed to be deployed together seamlessly using Cloudflare Pages.

1.  **Prepare your Git Repository:** Ensure all the latest code, including the `frontend/functions/_worker.js` file, is pushed to your Git repository (e.g., on GitHub).

2.  **Create a Cloudflare Pages Project:**
    *   Log in to your Cloudflare dashboard.
    *   Go to **Workers & Pages** > **Create application** > **Pages** > **Connect to Git**.
    *   Select your repository.

3.  **Configure the Build & Deployment:**
    *   In the "Build settings" section, provide the following configuration:
        *   **Project name:** Choose a name (e.g., `chat-log-parser`).
        *   **Framework preset:** `Vite`
        *   **Build command:** `npm run build`
        *   **Build output directory:** `build`
        *   **Root directory:** `frontend`
    *   Click **Save and Deploy**. Cloudflare will now build your frontend and deploy it along with the `_worker.js` function. Your application will be available at the domain provided by Pages (e.g., `https://ss.wenxiuxiu.eu.org`).

### Step 4: Configure Email Routing

1.  In your Cloudflare dashboard, go to **Email** > **Email Routing**.
2.  Ensure your domain's MX records are configured to use Cloudflare.
3.  Go to the **Routes** tab and click **Create address**.
4.  **Custom address:** Enter the username you want (e.g., `chat`).
5.  **Action:** Select **Send to a Worker**.
6.  **Worker:** Select the Pages project you just deployed (e.g., `chat-log-parser`).
7.  Click **Save**.

After completing these steps, your entire application should be live and fully functional.
