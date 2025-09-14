# Backend

This is the backend of the application, built with PHP.

## Setup

To set up the backend, you need to have PHP and Composer installed.

1.  Navigate to the `backend` directory:
    ```bash
    cd backend
    ```
2.  Install the dependencies:
    ```bash
    composer install
    ```
3.  Copy the `config.php.example` to `config.php` and update the database and Telegram bot settings.

## Running the Application

To run the application, you need to have a web server (like Apache or Nginx) with PHP support. Point the web server to the `backend/api` directory.

Alternatively, you can use the built-in PHP web server for development:

```bash
php -S localhost:8000 -t api
```

This will start the development server on `http://localhost:8000`.
