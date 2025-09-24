export default {
  async fetch(request, env, ctx) {
    const backendHost = 'https://wenge.cloudns.ch';
    const url = new URL(request.url);

    // Check if the request is for a static asset. If not, proxy to the API backend.
    // A simple heuristic: check for a file extension.
    const isStaticAsset = /\.[a-zA-Z0-9]+$/.test(url.pathname);

    if (!isStaticAsset && url.pathname !== '/') {
      // This is an API call.

      // Handle CORS pre-flight (OPTIONS) requests
      if (request.method === 'OPTIONS') {
        return new Response(null, {
          status: 204,
          headers: {
            'Access-Control-Allow-Origin': '*',
            'Access-Control-Allow-Methods': 'GET,POST,PUT,DELETE,OPTIONS',
            'Access-Control-Allow-Headers': 'Content-Type, Authorization, X-Requested-With',
            'Access-Control-Max-Age': '86400',
          },
        });
      }

      // Extract the action from the path, e.g., /login -> login
      const action = url.pathname.substring(1);

      // Preserve original search parameters from the frontend request
      const searchParams = new URLSearchParams(url.search);
      // Set the action for the backend PHP router
      searchParams.set('action', action);

      // Construct the new URL to point to the single index.php endpoint
      const backendUrl = new URL(`${backendHost}/index.php?${searchParams.toString()}`);

      // Forward request with intelligent header filtering
      const newHeaders = new Headers();
      for (const [k, v] of request.headers.entries()) {
        if (!/^host$/i.test(k) && !/^cf-/i.test(k) && !/^sec-/i.test(k)) {
          newHeaders.set(k, v);
        }
      }
      newHeaders.set('Host', new URL(backendHost).hostname);

      const init = {
        method: request.method,
        headers: newHeaders,
        redirect: 'follow',
      };

      if (request.method !== 'GET' && request.method !== 'HEAD') {
        init.body = await request.clone().arrayBuffer();
      }

      let backendResp;
      try {
        backendResp = await fetch(backendUrl, init);
      } catch (error) {
        console.error('Error proxying to backend:', error.message);
        return new Response('API backend unavailable.', { status: 502 });
      }

      // Return response with forced CORS headers
      const respHeaders = new Headers(backendResp.headers);
      respHeaders.set('Access-Control-Allow-Origin', '*');
      respHeaders.set('Access-Control-Allow-Methods', 'GET,POST,PUT,DELETE,OPTIONS');

      return new Response(backendResp.body, {
        status: backendResp.status,
        statusText: backendResp.statusText,
        headers: respHeaders,
      });
    }

    return env.ASSETS.fetch(request);
  }
};
