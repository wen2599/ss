// frontend/public/_worker.js

export default {
  async fetch(request, env, ctx) {
    const url = new URL(request.url);
    const pathname = url.pathname;
    let backendUrl;

    // --- Simplified Routing Logic ---
    if (pathname.startsWith('/api/')) {
      backendUrl = new URL("https://ss.wenxiuxiu.eu.org/api.php");
      backendUrl.search = url.search;
    } else if (pathname === '/webhook.php' || pathname === '/webhook_test.php' || pathname === '/test_final.php') {
      backendUrl = new URL("https://ss.wenxiuxiu.eu.org" + pathname);
    } else {
      // Not a backend request, serve from Pages assets.
      return env.ASSETS.fetch(request);
    }

    // --- Simplified Proxy Logic ---
    // This is the most basic and robust way to proxy a request.
    try {
      // Clone the original request but change the URL to point to the backend.
      const backendRequest = new Request(backendUrl, request);

      // Fetch the response from the backend.
      const backendResponse = await fetch(backendRequest);

      // Create a new response to add CORS headers, as the original is immutable.
      const response = new Response(backendResponse.body, backendResponse);
      response.headers.set("Access-Control-Allow-Origin", "*");
      response.headers.set("Access-Control-Allow-Methods", "GET, POST, PUT, DELETE, OPTIONS");
      response.headers.set("Access-Control-Allow-Headers", "Content-Type, Authorization, X-Telegram-Bot-Api-Secret-Token");

      return response;
      
    } catch (error) {
      // If the fetch to the backend fails (e.g., DNS error, network issue)
      const errorResponse = {
        success: false,
        message: 'Cloudflare Worker failed to connect to the backend server.',
        error: { name: error.name, message: error.message }
      };
      return new Response(JSON.stringify(errorResponse), {
        status: 503, // Service Unavailable
        headers: { 'Content-Type': 'application/json', 'Access-Control-Allow-Origin': '*' }
      });
    }
  },
};
