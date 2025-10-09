export default {
  async fetch(request, env) {
    const url = new URL(request.url);

    // --- API Request Proxying and Rewriting ---
    // This routes all /api/ requests to the backend's index.php router.
    if (url.pathname.startsWith('/api/')) {
      // 1. Extract the endpoint name from the path.
      // e.g., /api/getLotteryNumber -> getLotteryNumber
      const endpoint = url.pathname.replace('/api/', '');

      // 2. Construct the real backend URL pointing to the index.php router.
      const backendUrl = new URL(`https://wenge.cloudns.ch/backend/public/index.php?endpoint=${endpoint}`);

      // 3. Preserve original query parameters from the frontend request.
      if (url.search) {
          backendUrl.search += (backendUrl.search ? '&' : '') + url.search.slice(1);
      }

      // 4. Create a new request to the rewritten backend URL.
      const backendRequest = new Request(backendUrl.toString(), request);
      backendRequest.headers.set('Host', 'wenge.cloudns.ch');

      try {
        // 5. Fetch the response from the backend.
        const backendResponse = await fetch(backendRequest);
        const newHeaders = new Headers(backendResponse.headers);

        // 6. Set dynamic CORS headers to allow the frontend to access the API.
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

    // --- Static Asset Serving ---
    return env.ASSETS.fetch(request);
  },
};