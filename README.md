# Project Core Configuration

**IMPORTANT: This project has specific setup requirements. Read this section carefully before deploying or developing.**

### 1. No Composer Allowed
This project is intentionally designed to be **dependency-free**. 

- **DO NOT** run `composer install` or `composer update`.
- **DO NOT** add a `composer.json` file to the project.

All necessary functionalities, including environment variable loading, are handled by native PHP scripts included in the repository.

### 2. Environment Configuration (`.env`)
All environment-specific settings (like database credentials and API keys) are managed through a single `.env` file.

- This file **MUST** be placed in the **project root directory** (the same level as the `backend` and `frontend` folders).
- The backend scripts automatically look for this file one level above their own directory (e.g., `backend/../.env`).

### 3. Backend Bootstrapping
All PHP scripts in the `backend/` directory are initialized through a unified bootstrap file:

- **File Path**: `backend/bootstrap.php`
- **Function**: This script handles loading the `.env` file (using the native loader at `backend/load_env.php`) and establishes the global database connection (`$db_connection`).
- **Rule**: Any new PHP script you create in the backend should start by including this file: `require_once __DIR__ . '/bootstrap.php';`

---

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
