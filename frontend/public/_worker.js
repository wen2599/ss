export default {
  async fetch(request, env, ctx) {
    const backendHost = 'https://wenge.cloudns.ch';
    const url = new URL(request.url);
    const { pathname } = url;

    // Define all known API routes (actions) that should be proxied to the backend
    const apiRoutes = [
      '/check_session',
      '/email_upload',
      '/get_bills',
      '/get_game_data',
      '/get_lottery_results',
      '/is_user_registered',
      '/login',
      '/logout',
      '/process_text',
      '/register'
    ];

    // Check if the request is for an API route
    if (apiRoutes.includes(pathname)) {
      // This is an API call. Proxy it to the backend.

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
      const action = pathname.substring(1);

      // Preserve original search parameters from the frontend request
      const searchParams = new URLSearchParams(url.search);
      // Set the action for the backend PHP router
      searchParams.set('action', action);

      // Construct the new URL to point to the single index.php endpoint
      const backendUrl = new URL(`${backendHost}/index.php?${searchParams.toString()}`);

      const newHeaders = new Headers(request.headers);
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

      const respHeaders = new Headers(backendResp.headers);
      respHeaders.set('Access-Control-Allow-Origin', '*');
      respHeaders.set('Access-Control-Allow-Methods', 'GET,POST,PUT,DELETE,OPTIONS');

      return new Response(backendResp.body, {
        status: backendResp.status,
        statusText: backendResp.statusText,
        headers: respHeaders,
      });
    }

    // For all other requests, assume it's a static asset or a SPA route.
    // Let Cloudflare Pages' asset handling take over.
    // It will serve the file if it exists, or serve the SPA fallback (index.html)
    // if a _redirects file with `/* /index.html 200` is configured,
    // or if SPA fallback is enabled in the Pages settings.
    // A simple try-catch can also work as a fallback.
    try {
      return await env.ASSETS.fetch(request);
    } catch (e) {
      // If the asset is not found, fall back to the main index.html page
      // This is crucial for SPA routing to work correctly.
      let notFoundResponse = await env.ASSETS.fetch(new URL('/', url).toString());
      return new Response(notFoundResponse.body, {
        ...notFoundResponse,
        status: 200
      });
    }
  }
};
