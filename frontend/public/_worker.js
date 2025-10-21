// frontend/public/_worker.js

export default {
  async fetch(request, env) {
    const url = new URL(request.url);
    const backendServer = "https://wenge.cloudns.ch"; // Your backend server

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
      const backendUrl = new URL(url.pathname, backendServer);
      backendUrl.search = url.search;

      const backendRequest = new Request(backendUrl.toString(), {
        method: request.method,
        headers: request.headers,
        body: request.body,
        redirect: 'follow',
      });

      try {
        const response = await fetch(backendRequest);
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
    return env.ASSETS.fetch(request);
  },
};
