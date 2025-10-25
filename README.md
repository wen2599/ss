# Project API Documentation

This document outlines all the available API endpoints for the backend application.

Assuming your backend is hosted at `https://<YOUR_DOMAIN>`, here are the endpoints you can use.

---

## Core API Endpoints

These are the primary interfaces for front-end applications or other services.

### Process Incoming Emails
- **Path**: `https://<YOUR_DOMAIN>/api.php`
- **Method**: `POST`
- **Function**: This endpoint is designed for an email processing worker (like a Cloudflare Email Worker). It receives a JSON object containing the email's `from`, `subject`, and `body`. The server checks if the sender is a registered user and, if so, parses and saves the email content.

### User Registration
- **Path**: `https://<YOUR_DOMAIN>/api/register.php`
- **Method**: `POST`
- **Function**: Accepts a JSON object with `email` and `password`. Creates a new user in the database.

### User Login
- **Path**: `https://<YOUR_DOMAIN>/api/login.php`
- **Method**: `POST`
- **Function**: Accepts `email` and `password`. Upon successful validation, it returns a JSON Web Token (JWT) for authenticating subsequent requests.

### Get User Records
- **Path**: `https://<YOUR_DOMAIN>/api/get-my-records.php`
- **Method**: `GET`
- **Function**: Requires a `Bearer <token>` in the `Authorization` header. If the token is valid, it returns all saved email records for that user.

---

## Telegram Bot Endpoint

This endpoint is the webhook for the Telegram bot.

### Telegram Webhook
- **Path**: `https://<YOUR_DOMAIN>/bot.php`
- **Method**: `POST`
- **Function**: This is the URL to be configured in your Telegram Bot's settings. It handles all updates from Telegram, including:
    - **Channel Posts**: Silently listens for new messages in a channel where the bot is an admin, attempting to parse and save lottery draw data.
    - **Admin Commands**: Responds to private commands sent by the designated admin (e.g., `/stats`).

---

## Database Setup Script

This is a one-time installation script.

### Database Initialization
- **Path**: `https://<YOUR_DOMAIN>/auth_setup.php`
- **Method**: `GET` (or accessed directly in a browser)
- **Function**: Creates the necessary `users` and `emails` tables in the database.
- **SECURITY WARNING**: This script is a major security risk if left on a public server. After running it successfully for the first time, you **MUST** delete the `backend/auth_setup.php` file immediately to prevent unauthorized access or abuse.
