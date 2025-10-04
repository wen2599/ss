# Lottery Number Display App

This is a full-stack application that displays lottery numbers. The frontend is built with React and Vite, and the backend is a simple PHP application. It also includes a Telegram bot integration for updating the lottery numbers and a user authentication system.

This project uses a modern architecture:
- **Backend**: A PHP application using a "front controller" pattern (`index.php`) to route all API requests.
- **Frontend**: A React application that leverages a Cloudflare Worker (`_worker.js`) to proxy API requests, solving CORS issues and simplifying deployment.

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
    Open the `.env` file and add your actual Telegram bot token and a secure secret key.
    ```
    TELEGRAM_BOT_TOKEN="YOUR_TELEGRAM_BOT_TOKEN"
    UPDATE_SECRET="YOUR_RANDOMLY_GENERATED_SECRET_KEY"
    ```

4.  **Run the PHP server**:
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
    You need to tell Telegram where to send messages. The URL should point to your deployed backend.
    Replace `YOUR_TELEGRAM_BOT_TOKEN` and `YOUR_SERVER_URL` in the URL below:
    ```
    https://api.telegram.org/bot<YOUR_TELEGRAM_BOT_TOKEN>/setWebhook?url=<YOUR_SERVER_URL>/api/telegram_webhook.php
    ```
    **Note**: `YOUR_SERVER_URL` should be the base URL of your deployed backend (e.g., `https://your-username.serv00.net`).

3.  **Update Lottery Numbers**:
    Send a message to your bot in the following format:
    `/update YYYYMMDD num1,num2,num3,num4,num5,num6`
    Example:
    `/update 20251004 5,12,18,22,31,42`

## Deployment

### Backend on Serv00

1.  **Upload the `backend` directory** to your Serv00 server's public web directory (e.g., `public_html`).
2.  **Configure URL Rewriting**: You need to configure your web server (e.g., Apache) to route all requests for non-existent files to `index.php`. Create a `.htaccess` file in the `backend` directory with the following content:
    ```apache
    RewriteEngine On
    RewriteBase /
    RewriteCond %{REQUEST_FILENAME} !-f
    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteRule . /index.php [L]
    ```
3.  **Set up the `.env` file** on the server with your production secrets. **Do not upload your `.env` file directly.**
4.  Set the Telegram webhook to point to your live server URL as described above.

### Frontend on Cloudflare Pages

1.  **Push your code** to a GitHub repository.
2.  **Configure the Backend URL in the Worker**:
    - Open `frontend/public/_worker.js`.
    - Change the `backendUrl` constant to your live backend URL (e.g., `https://your-username.serv00.net`).
    ```javascript
    const backendUrl = 'https://your-serv00-backend.com'; // CHANGE THIS
    ```
3.  **Create a new project** on Cloudflare Pages and connect it to your GitHub repository.
4.  **Configure the build settings**:
    - **Framework preset**: `Vite`
    - **Build command**: `npm run build`
    - **Build output directory**: `dist`
    - **Root directory**: `frontend`
5.  **Deploy the site**. Cloudflare Pages will automatically find the `_worker.js` file and use it to handle requests. This setup proxies all `/api/` calls to your backend, eliminating any CORS issues.