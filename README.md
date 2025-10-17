# Project Architecture Overview

This document provides a high-level overview of the project's architecture, focusing on the communication flow between the frontend, backend, and various services.

## Core Components

-   **Frontend:** A single-page application built with React and Vite, located in the `frontend/` directory.
-   **Backend:** A pure PHP application located in the `backend/` directory. It serves as the API for the frontend and handles business logic.
-   **Database:** A MySQL database that stores all application data.

## Communication Flow

### Frontend <-> Backend API

Direct communication between the frontend and the PHP backend is proxied through a Cloudflare Worker for security and flexibility.

1.  **Frontend Request:** The React application (running in the user's browser) makes an API call to a clean, semantic path, such as `/api/login_user`.
2.  **Cloudflare Worker (`frontend/public/_worker.js`):** The request is intercepted by a Cloudflare Worker deployed alongside the frontend assets. This worker's logic is defined in `frontend/public/_worker.js`.
3.  **Request Proxying:** The worker modifies the request path. It strips the `/api/` prefix and appends the `.php` extension. For example, `/api/login_user` becomes `/login_user.php`.
4.  **Backend Target:** The modified request is then forwarded to the actual backend server, hosted at `https://wenge.cloudns.ch`.
5.  **Backend Processing:** The PHP backend receives the request as a direct call to the corresponding `.php` file (e.g., `login_user.php`) and processes it.
6.  **Response:** The response from the PHP script is sent back through the worker to the frontend application.

This architecture decouples the frontend from the backend's file structure, allowing for cleaner API calls while maintaining a traditional PHP file-based endpoint structure on the server.

### Email Processing

Incoming emails are processed via a separate, dedicated Cloudflare Worker that listens for email events.

1.  **Email Reception:** An email is sent to a designated address that is configured to trigger a Cloudflare Email Worker.
2.  **Email Worker (`worker/worker.js`):** The logic for this worker is defined in `worker/worker.js`. This worker is deployed separately from the main application.
3.  **Email Parsing & Forwarding:** The worker parses the content of the incoming email.
4.  **Backend Endpoint:** It then forwards the parsed email data to a specific endpoint on the PHP backend (e.g., `/email_upload`) for storage and further processing.

This setup allows for robust and scalable email handling without directly exposing an email server or requiring the main backend to handle complex email parsing protocols.