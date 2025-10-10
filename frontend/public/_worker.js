export default {
  async fetch(request, env) {
    const url = new URL(request.url);

    // This is the definitive fix for the routing issue.
    // All /api/ requests are rewritten to the single, correct backend router.
    if (url.pathname.startsWith('/api/')) {
      // 1. Extract the endpoint name from the path (e.g., /api/getLotteryNumber -> getLotteryNumber).
      const endpoint = url.pathname.replace('/api/', '');

      // 2. Construct the absolute, correct backend URL.
      // This rewrite points to the main router file that handles all API requests.
      // NOTE: The path is /public/index.php because the server's document root is the `backend` directory.
      const backendUrl = new URL(`https://wenge.cloudns.ch/public/index.php?endpoint=${endpoint}`);

      // 3. Preserve original query parameters from the frontend request.
      if (url.search) {
          backendUrl.search += (backendUrl.search ? '&' : '') + url.search.slice(1);
      }

      // 4. Create a new request to the rewritten backend URL.
      const backendRequest = new Request(backendUrl.toString(), request);
      backendRequest.headers.set('Host', 'wenge.cloudns.ch');

      try {
        // 5. Fetch and return the response from the backend.
        const backendResponse = await fetch(backendRequest);
        const newHeaders = new Headers(backendResponse.headers);

        // 6. Set dynamic CORS headers.
        newHeaders.set('Access-Control-Allow-Origin', new URL(request.url).origin);
        newHeaders.set('Vary', 'Origin');
        newHeaders.set('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS');
        newHeaders.set('Access-Control-Allow-Headers', 'Content-Type, Authorization');
        
        return new Response(backendResponse.body, {
          status: backendResponse.status,
          statusText: backendResponse.statusText,
          headers: newHeaders
        });

      } catch (error) {
        return new Response(`Error proxying to backend: ${error.message}`, { status: 502 });
      }
    }

    // For non-API requests, serve the static assets from Cloudflare Pages.
    return env.ASSETS.fetch(request);
  },
};