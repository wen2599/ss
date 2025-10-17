// frontend/public/_worker.js

export default {
  async fetch(request, env) {
    const url = new URL(request.url);
    const backendServer = "https://wenge.cloudns.ch"; // Your backend server

// If the request is for our API, proxy it to the backend.
if (url.pathname.startsWith('/api/')) {
  // 1. Get the original path (e.g., /api/login_user)
  const originalPath = url.pathname;

  // 2. Create the new path for the PHP backend.
  // It strips '/api/' and appends '.php'.
  // e.g., /api/login_user -> /login_user.php
  const newPath = originalPath.substring(4) + '.php';

  // 3. Construct the full backend URL.
  const backendUrl = new URL(newPath, backendServer);
  backendUrl.search = url.search; // Keep original query parameters.

  // 4. Create a new request object to send to the backend.
      const backendRequest = new Request(backendUrl.toString(), {
        method: request.method,
        headers: request.headers,
        body: request.body,
        redirect: 'follow',
    // duplex: 'half' is often required for POST/PUT requests in workers
    ...(request.method !== 'GET' && request.method !== 'HEAD' && { duplex: 'half' }),
      });

      try {
    // 5. Fetch the response from the backend and return it to the client.
    console.log(`Proxying request from ${originalPath} to ${backendUrl.toString()}`);
        return await fetch(backendRequest);
      } catch (error) {
    console.error(`Backend fetch for ${backendUrl.toString()} failed: ${error.message}`);
        return new Response('Bad Gateway', { status: 502, statusText: 'Bad Gateway' });
      }
    }

    // Otherwise, serve the static assets from the Pages deployment.
    return env.ASSETS.fetch(request);
  },
};