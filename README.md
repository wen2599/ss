# Lottery Number Display App

This is a full-stack application that displays lottery numbers. The frontend is built with React and Vite, and the backend is a simple PHP application. It also includes a Telegram bot integration for updating the lottery numbers.

## Project Structure

- `frontend/`: Contains the React + Vite frontend application.
- `backend/`: Contains the PHP backend, including the API and data storage.

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
    You can use the built-in PHP development server.
    ```bash
    php -S localhost:8000 -t .
    ```
    This will serve the `api` and `data` directories correctly from the root of the backend folder.

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
    The application will be available at `http://localhost:5173` (or another port if 5173 is in use). The Vite development server is configured to proxy requests to `/api` to your backend at `http://localhost:8000`.

## Telegram Bot Integration

1.  **Create a Telegram Bot**:
    - Talk to the `@BotFather` on Telegram to create a new bot.
    - It will give you a unique token. Add this token to your `backend/.env` file.

2.  **Set the Webhook**:
    You need to tell Telegram where to send messages that your bot receives. You can do this by making a GET request to the Telegram API.
    Replace `YOUR_TELEGRAM_BOT_TOKEN` and `YOUR_SERVER_URL` in the URL below:
    ```
    https://api.telegram.org/bot<YOUR_TELEGRAM_BOT_TOKEN>/setWebhook?url=<YOUR_SERVER_URL>/api/telegram_webhook.php
    ```
    For local testing, you can use a tool like `ngrok` to expose your local server to the internet.

3.  **Update Lottery Numbers**:
    Send a message to your bot in the following format:
    `/update YYYYMMDD num1,num2,num3,num4,num5,num6`
    Example:
    `/update 20251004 5,12,18,22,31,42`

## Deployment

### Backend on Serv00

1.  **Upload the `backend` directory** to your Serv00 server.
2.  Ensure your web server (e.g., Apache) is configured to serve PHP files.
3.  Set up the `.env` file on the server with your production secrets. **Do not upload your `.env` file directly.**
4.  Set the Telegram webhook to point to your live server URL.

### Frontend on Cloudflare Pages

1.  **Push your code** to a GitHub repository.
2.  **Create a new project** on Cloudflare Pages and connect it to your GitHub repository.
3.  **Configure the build settings**:
    - **Framework preset**: `Vite`
    - **Build command**: `npm run build`
    - **Build output directory**: `dist`
    - **Root directory**: `frontend`
4.  **Deploy the site**. Cloudflare will automatically build and deploy your React application.
5.  You may need to configure proxying or redirects in your Cloudflare Pages settings if you want to serve the backend from the same domain. A simpler approach is to use the full backend URL in your frontend code for production builds.