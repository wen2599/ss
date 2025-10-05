export default {
  async fetch(request, env) {
    const url = new URL(request.url);

    // --- API Proxying ---
    // If the request is for our API, proxy it to the backend.
    if (url.pathname.startsWith('/api/')) {
      // The backend server URL for production.
      const backendUrl = 'https://wenge.cloudns.ch';

      // Create a new URL to the backend by taking the original path and query.
      const newUrl = new URL(backendUrl + url.pathname + url.search);

      // Create a new request to the backend, copying the original request's method, headers, and body.
      const newRequest = new Request(newUrl, {
        method: request.method,
        headers: request.headers,
        body: request.body,
        redirect: 'follow'
      });

      // Forward the request to the backend and return the response.
      return fetch(newRequest);
    }

    // --- Static Asset Serving ---
    // For all other requests, let Cloudflare Pages serve the static assets (your React app).
    // The `env.ASSETS.fetch()` is a special function provided by Cloudflare Pages that
    // serves the deployed static files.
    return env.ASSETS.fetch(request);
  },
};