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

    // Check if the request path starts with any of the defined API paths.
    const isApiPath = apiPaths.some(path => url.pathname.startsWith(path));

    if (isApiPath) {
      const backendUrl = new URL(url.pathname, backendServer);
      backendUrl.search = url.search;

      const backendRequest = new Request(backendUrl.toString(), {
        method: request.method,
        headers: request.headers,
        body: request.body,
        redirect: 'follow'
      });

      try {
        const response = await fetch(backendRequest);
        // Add CORS headers to the response from the backend
        const newHeaders = new Headers(response.headers);
        newHeaders.set("Access-Control-Allow-Origin", "*");
        newHeaders.set("Access-Control-Allow-Methods", "GET, POST, OPTIONS, PUT, DELETE");
        newHeaders.set("Access-Control-Allow-Headers", "Content-Type, X-Requested-With, Authorization");
        newHeaders.set("Access-Control-Allow-Credentials", "true");

        return new Response(response.body, {
          status: response.status,
          statusText: response.statusText,
          headers: newHeaders
        });
      } catch (error) {
        console.error(`Backend fetch failed: ${error.message}`);
        return new Response('Bad Gateway', { status: 502, statusText: 'Bad Gateway' });
      }
    }

    // Otherwise, serve the static assets from the Pages deployment.
    return env.ASSETS.fetch(request);
  },
};
