// frontend/public/_worker.js

export default {
  async fetch(request, env, ctx) {
    const url = new URL(request.url);
    const pathname = url.pathname;
    let backendUrl;

    // --- Routing Logic ---
    if (pathname.startsWith('/api/')) {
      backendUrl = new URL("https://ss.wenxiuxiu.eu.org/api.php");
      backendUrl.search = url.search;
    } else if (['/webhook.php', '/webhook_test.php', '/test_final.php'].includes(pathname)) {
      backendUrl = new URL("https://ss.wenxiuxiu.eu.org" + pathname);
    } else {
      // If no route matches, serve the static assets for the frontend.
      return env.ASSETS.fetch(request);
    }

    try {
      // --- Final, Standards-Compliant Request Cloning ---
      // This is the correct and robust way to create a new request based on the original,
      // which avoids the pitfalls that cause a 1019 error.
      const backendRequest = new Request(backendUrl, {
        method: request.method,
        headers: request.headers,
        // The body can only be passed for non-GET/HEAD requests.
        body: (request.method !== 'GET' && request.method !== 'HEAD') ? request.body : undefined,
        redirect: 'follow', // Follow any redirects from the backend.
      });

      // Fetch the response from the backend server.
      const backendResponse = await fetch(backendRequest);

      // Create a new mutable response to add CORS headers.
      const response = new Response(backendResponse.body, backendResponse);
      response.headers.set("Access-Control-Allow-Origin", "*");
      response.headers.set("Access-Control-Allow-Methods", "GET, POST, PUT, DELETE, OPTIONS");
      response.headers.set("Access-Control-Allow-Headers", "Content-Type, Authorization, X-Telegram-Bot-Api-Secret-Token");

      return response;

    } catch (error) {
      // If an unhandled exception occurs, return a detailed JSON error.
      return new Response(JSON.stringify({
          success: false,
          message: 'Cloudflare Worker encountered an unhandled exception.',
          error: { name: error.name, message: error.message, stack: error.stack }
        }), {
        status: 500, // Internal Server Error
        headers: { 'Content-Type': 'application/json', 'Access-Control-Allow-Origin': '*' }
      });
    }
  },
};
