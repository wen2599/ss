// frontend/public/_worker.js

export default {
  async fetch(request, env) {
    const url = new URL(request.url);
    // Use the BACKEND_URL from environment variables, with a fallback.
    const backendServer = env.BACKEND_URL || "https://wenge.cloudns.ch";

    // Define the API paths that should be proxied to the backend.
    const apiPaths = [
      '/login',
      '/register',
      '/logout',
      '/check_session',
      '/get_bills',
      '/delete_bill',
      '/get_lottery_results',
      '/telegram_webhook',
      '/email_webhook',
      '/admin'
    ];

    // Check if the request path is an exact match or a sub-path of an API path.
    const isApiPath = apiPaths.some(path => url.pathname === path || url.pathname.startsWith(path + '/'));

    if (isApiPath) {
      // Handle CORS preflight requests directly.
      if (request.method === 'OPTIONS') {
        return new Response(null, {
          status: 204, // No Content
          headers: {
            'Access-Control-Allow-Origin': '*',
            'Access-Control-Allow-Methods': 'GET, POST, OPTIONS, PUT, DELETE',
            'Access-Control-Allow-Headers': 'Content-Type, X-Requested-With, Authorization',
            'Access-Control-Allow-Credentials': 'true',
            'Access-Control-Max-Age': '86400', // Cache for 1 day
          },
        });
      }

      // Proxy other API requests to the backend.
      const backendUrl = new URL(url.pathname + url.search, backendServer);

      try {
        // Use a more direct fetch method, passing the original request object
        // through with the new URL. This is often more stable.
        const response = await fetch(backendUrl.toString(), request);

        // Clone the response to make headers mutable.
        const newResponse = new Response(response.body, response);
        newResponse.headers.set('Access-Control-Allow-Origin', '*');
        newResponse.headers.set('Access-Control-Allow-Credentials', 'true');
        return newResponse;
      } catch (error) {
        console.error(`Backend fetch failed: ${error.message}`);
        return new Response('Bad Gateway', { status: 502, statusText: 'Bad Gateway' });
      }
    }

    // For non-API paths, serve the static assets from the Pages deployment.
    // Defensive check: If the ASSETS binding is not available, return a clear error.
    if (!env.ASSETS) {
      return new Response('Static asset binding not found. This is a deployment configuration issue.', {
        status: 404,
        statusText: 'Not Found',
      });
    }

    try {
      return await env.ASSETS.fetch(request);
    } catch (e) {
      // If the asset fetch fails, return a 404.
      return new Response(null, { status: 404 });
    }
  },
};
