export default {
  async fetch(request, env) {
    const url = new URL(request.url);

    // --- API Request Proxying and Rewriting ---
    if (url.pathname.startsWith('/api/')) {
      // 1. Extract the endpoint name from the path.
      // e.g., /api/getLotteryNumber -> getLotteryNumber
      const endpoint = url.pathname.replace('/api/', '');

      // 2. Construct the real backend URL that the PHP server understands.
      // This rewrites the "pretty" URL to the actual script path.
      const backendUrl = new URL(`https://wenge.cloudns.ch/public/index.php?endpoint=${endpoint}`);

      // Preserve original query parameters if any (for future features like pagination)
      if (url.search) {
          backendUrl.search += (backendUrl.search ? '&' : '') + url.search.slice(1);
      }

      // 3. Create a new request to the rewritten backend URL.
      const backendRequest = new Request(backendUrl.toString(), {
        method: request.method,
        headers: request.headers,
        body: request.body,
        redirect: 'follow'
      });

      // 4. Set the Host header, which is a good practice for proxies.
      backendRequest.headers.set('Host', 'wenge.cloudns.ch');

      try {
        // 5. Fetch the response from the backend.
        const backendResponse = await fetch(backendRequest);
        const newHeaders = new Headers(backendResponse.headers);

        // 6. Set CORS headers to allow the frontend to access the API.
        newHeaders.set('Access-Control-Allow-Origin', new URL(request.url).origin);
        newHeaders.set('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS');
        newHeaders.set('Access-Control-Allow-Headers', 'Content-Type, Authorization');
        
        // 7. Return the backend's response with the correct CORS headers.
        return new Response(backendResponse.body, {
          status: backendResponse.status,
          statusText: backendResponse.statusText,
          headers: newHeaders
        });

      } catch (error) {
        return new Response(`Error proxying to backend: ${error.message}`, { status: 502 });
      }
    }

    // --- Static Asset Serving ---
    // For non-API requests, serve the static assets from Cloudflare Pages.
    return env.ASSETS.fetch(request);
  },
};