/**
 * Welcome to Cloudflare Workers!
 *
 * This worker acts as an API Gateway, proxying and rewriting frontend API
 * requests to the backend server.
 *
 * This version is streamlined to focus solely on the API proxy functionality,
 * removing all email handling logic to resolve module import errors.
 */

export default {
  /**
   * Handles all incoming HTTP requests.
   * - If the path starts with /api/, it acts as an API gateway.
   * - Otherwise, it serves static assets for the frontend application.
   */
  async fetch(request, env, ctx) {
    const url = new URL(request.url);
    const backendServer = env.PUBLIC_API_ENDPOINT || "https://wenge.cloudns.ch";

    // --- API Gateway Logic ---
    if (url.pathname.startsWith('/api/')) {
      // Any /api/ request is proxied.
      const endpoint = url.pathname.substring(5);

      // This path is definitive based on our successful debugging.
      const backendUrl = new URL(`${backendServer}/public/index.php?endpoint=${endpoint}`);

      // Preserve original query parameters
      backendUrl.search = url.search;

      const requestHeaders = new Headers(request.headers);
      requestHeaders.set('Host', new URL(backendServer).hostname);

      // Add Telegram secret header if it's the webhook endpoint.
      if (endpoint === 'telegramWebhook' && env.TELEGRAM_WEBHOOK_SECRET) {
        requestHeaders.set('X-Telegram-Bot-Api-Secret-Token', env.TELEGRAM_WEBHOOK_SECRET);
      }

      try {
        const backendRequest = new Request(backendUrl.toString(), new Request(request, { headers: requestHeaders }));
        const backendResponse = await fetch(backendRequest);

        // Create a new response with mutable headers to add CORS.
        const responseHeaders = new Headers(backendResponse.headers);
        const origin = request.headers.get('Origin');
        if (origin) {
          responseHeaders.set('Access-Control-Allow-Origin', origin);
          responseHeaders.set('Vary', 'Origin');
        }
        responseHeaders.set('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS');
        responseHeaders.set('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Telegram-Bot-Api-Secret-Token');

        return new Response(backendResponse.body, {
          status: backendResponse.status,
          statusText: backendResponse.statusText,
          headers: responseHeaders,
        });

      } catch (error) {
        console.error(`[Worker Fetch Error] Failed to proxy to backend: ${error.message}`);
        const errorResponse = { error: 'Backend Proxy Error', message: error.message };
        return new Response(JSON.stringify(errorResponse), {
          status: 502,
          headers: {
            'Content-Type': 'application/json',
            'Access-Control-Allow-Origin': '*'
          }
        });
      }
    }

    // --- Static Asset Serving ---
    return env.ASSETS.fetch(request);
  }
};