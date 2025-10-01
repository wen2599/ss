export default {
  async fetch(request, env, ctx) {
    const url = new URL(request.url);
    // The backend is hosted on the same domain as the worker.
    // We use the request's URL origin to dynamically determine the backend host.
    const backendHost = url.origin;
    const { pathname } = url;

    // The origin of the frontend application making the request
    const origin = request.headers.get('Origin');

    // Define all known API routes that should be proxied to the backend
    const apiRoutes = [
      '/check_session',
      '/email_upload',
      '/get_bills',
      '/delete_bill',
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
            'Access-Control-Allow-Origin': origin, // Use the dynamic origin
            'Access-Control-Allow-Methods': 'GET,POST,PUT,DELETE,OPTIONS',
            'Access-Control-Allow-Headers': 'Content-Type, Authorization, X-Requested-With',
            'Access-Control-Allow-Credentials': 'true', // Allow credentials
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
      // IMPORTANT: For credentialed requests, the origin cannot be a wildcard.
      // It must match the origin of the frontend request.
      respHeaders.set('Access-Control-Allow-Origin', origin);
      respHeaders.set('Access-Control-Allow-Credentials', 'true'); // Allow credentials
      respHeaders.set('Access-Control-Allow-Methods', 'GET,POST,PUT,DELETE,OPTIONS');

      return new Response(backendResp.body, {
        status: backendResp.status,
        statusText: backendResp.statusText,
        headers: respHeaders,
      });
    }

    // For all other requests, serve from static assets.
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