# Lottery Number Display App

This is a full-stack application that displays lottery numbers. The frontend is built with React and Vite, and the backend is a simple PHP application. It also includes a Telegram bot integration for updating the lottery numbers and a user authentication system.

This project uses a modern architecture:
- **Backend**: A PHP application using a "front controller" pattern (`index.php`) to route all API requests.
- **Frontend**: A React application that leverages a Cloudflare Worker (`_worker.js`) to proxy API requests, solving CORS issues and simplifying deployment.

## Production URLs
- **Frontend**: `https://ss.wenxiuxiu.eu.org`
- **Backend**: `https://wenge.cloudns.ch`

## Project Structure

- `frontend/`: Contains the React + Vite frontend application.
  - `public/_worker.js`: Cloudflare Worker script for proxying requests.
- `backend/`: Contains the PHP backend.
  - `index.php`: The single entry point (front controller) for all API requests.
  - `api_handler.php`: Contains the logic for all API endpoints.

## How to Set Up

### Backend

1.  **Navigate to the backend directory**:
    ```bash
    cd backend
    ```

2.  **Create an environment file**:
    Copy the example environment file to create your own local configuration.
    ```bash
    cp .env.example .env
    ```

3.  **Configure your secrets**:
    Open the `backend/.env` file and fill in your credentials. Below is a description of each variable from the `.env.example` file:

    - `DB_HOST`: The hostname of your database server (e.g., `localhost`).
    - `DB_USER`: Your database username.
    - `DB_PASS`: Your database password.
    - `DB_NAME`: The name of your database.
    - `TELEGRAM_BOT_TOKEN`: The token for your Telegram bot, obtained from `@BotFather`.
    - `TELEGRAM_ADMIN_ID`: Your personal Telegram User ID. This can be used to restrict certain bot commands to only you.
    - `TELEGRAM_CHANNEL_ID`: The ID of the Telegram channel or group where the bot will be active.

4.  **Run the PHP server for local development**:
    From the project root directory, run:
    ```bash
    php -S localhost:8000 -t backend
    ```
    This command starts a local development server where all requests are handled by `backend/index.php`.

### Frontend

1.  **Navigate to the frontend directory**:
    ```bash
    cd frontend
    ```

2.  **Install dependencies**:
    ```bash
    npm install
    ```

3.  **Run the development server**:
    ```bash
    npm run dev
    ```
    The application will be available at `http://localhost:5173` (or another port if 5173 is in use). The Vite development server is configured to proxy requests starting with `/api/` to your local PHP backend at `http://localhost:8000`.

## Telegram Bot Integration

1.  **Create a Telegram Bot**:
    - Talk to the `@BotFather` on Telegram to create a new bot.
    - It will give you a unique token. Add this token to your `backend/.env` file.

2.  **Set the Webhook**:
    You need to tell Telegram where to send messages. The URL must point to your deployed backend.
    Replace `YOUR_TELEGRAM_BOT_TOKEN` in the URL below. The server URL is your production backend.
    ```
    https://api.telegram.org/bot<YOUR_TELEGRAM_BOT_TOKEN>/setWebhook?url=https://wenge.cloudns.ch/api/telegram_webhook.php
    ```

3.  **Update Lottery Numbers**:
    Send a message to your bot in the following format:
    `/update YYYYMMDD num1,num2,num3,num4,num5,num6`
    Example:
    `/update 20251004 5,12,18,22,31,42`

## Deployment

### Backend on Serv00

1.  **Upload the `backend` directory** to your Serv00 server's public web directory (e.g., `public_html`).
2.  **Ensure URL Rewriting is Enabled**: The included `.htaccess` file automatically configures the Apache server to correctly route all requests to `index.php`. Make sure this file is uploaded with the rest of the `backend` directory. This is crucial for the API to work correctly.
3.  **Set up the `.env` file** on the server with your production secrets. **Do not upload your `.env` file directly.**
4.  Set the Telegram webhook to point to your live server URL (`https://wenge.cloudns.ch`) as described above.

### Frontend on Cloudflare Pages

1.  **Push your code** to a GitHub repository.
2.  **Confirm the Backend URL in the Worker**:
    - The `frontend/public/_worker.js` file has already been configured to use `https://wenge.cloudns.ch` as the backend.
    ```javascript
    const backendUrl = 'https://wenge.cloudns.ch';
    ```
3.  **Create a new project** on Cloudflare Pages and connect it to your GitHub repository.
4.  **Configure the build settings**:
    - **Framework preset**: `Vite`
    - **Build command**: `npm run build`
    - **Build output directory**: `dist`
    - **Root directory**: `frontend`
5.  **Deploy the site**. Your frontend will be live at `https://ss.wenxiuxiu.eu.org`. Cloudflare Pages will automatically use the `_worker.js` file to proxy all `/api/` calls to your backend.