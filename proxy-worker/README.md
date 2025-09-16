# Cloudflare Proxy Worker Deployment with Pages

This document provides instructions on how to deploy the `proxy.js` worker alongside your Cloudflare Pages frontend project. This setup allows the worker to handle API requests while Pages serves your static site, all from the same domain.

## Deployment Steps

The key to this setup is to place the worker script inside a specific directory in your frontend project before you deploy it to Cloudflare Pages.

### 1. Prepare Your Project Structure

1.  **Move the Worker:** Before you push your code to your Git repository, you need to copy or move the `proxy-worker/proxy.js` file into the `frontend/functions` directory. The file should be renamed to `_worker.js`. The final path should be `frontend/functions/_worker.js`.
    *   The `functions` directory is a special directory that Cloudflare Pages uses for its server-side logic.
    *   A `_worker.js` file in this directory will automatically be deployed as a single, powerful worker alongside your site.

2.  **Delete Old Files (Optional but Recommended):** You can delete the `proxy-worker` directory now, as its contents have been moved into the `frontend/functions` directory. You can also delete the `worker` directory from the previous email worker, as this new proxy worker replaces its functionality (you will need to re-configure your email routing to point to this new integrated worker if you still want that feature).

### 2. Configure Your Cloudflare Pages Project

1.  If you haven't already, create a new Cloudflare Pages project and connect it to your Git repository.
2.  During the setup, configure the **Build settings** as follows:
    *   **Framework preset:** `Vite`
    *   **Build command:** `npm run build`
    *   **Build output directory:** `build` (This is configured in our `vite.config.js`)
    *   **Root directory:** `frontend`

### 3. Deploy

1.  Commit and push all your changes (including the new `frontend/functions/_worker.js` file) to your Git repository.
2.  Cloudflare Pages will automatically detect the push and start a new deployment.
3.  The deployment will build your Vite frontend and deploy the `_worker.js` script alongside it.

## How It Works

-   Any request to your Pages domain (e.g., `https://ss.wenxiuxiu.eu.org`) that starts with `/api/` will be intercepted and handled by your `_worker.js` script. The script will proxy the request to your backend at `https://wenge.cloudns.ch`.
-   All other requests (e.g., for `/`, `/index.html`, etc.) will be handled by the Cloudflare Pages static asset server.
-   This creates a seamless experience where your frontend and API proxy are served from the same domain, completely solving all CORS issues for both web and native APK builds.
