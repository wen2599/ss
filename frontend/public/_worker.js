// File: frontend/public/_worker.js

export default {
  /**
   * @param {Request} request
   * @param {object} env
   * @param {object} ctx
   */
  async fetch(request, env, ctx) {
    const url = new URL(request.url);

    // Only proxy requests that start with /api/
    if (url.pathname.startsWith('/api/')) {
      // Extract the endpoint from the path, e.g., /api/register -> register
      const endpoint = url.pathname.substring(5);
      
      // Construct the new backend URL
      // Example: https://wenge.cloudns.ch/index.php?endpoint=register
      const backendUrl = `https://wenge.cloudns.ch/index.php?endpoint=${endpoint}${url.search}`;
      
      // Create a new request to the backend, copying the original request's properties.
      // This is a robust way to forward the request, including method, headers, and body.
      const backendRequest = new Request(backendUrl, request);

      // Forward the request to the backend and return the response.
      try {
        return await fetch(backendRequest);
      } catch (e) {
        // If the backend is unreachable, return a 503 error.
        return new Response(`Backend server is unreachable: ${e.message}`, { status: 503 });
      }
    }

    // For all other requests, serve the static assets from Cloudflare Pages.
    return env.ASSETS.fetch(request);
  },
};