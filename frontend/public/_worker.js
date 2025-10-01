export default {
  async fetch(request, env, ctx) {
    // The backend is hosted on a specific, hardcoded domain.
    // This prevents an infinite loop where the worker proxies requests to itself.
    const backendHost = 'https://ss.wenxiuxiu.eu.org';
    const url = new URL(request.url);
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
            'Access-Control-Allow-Origin': origin,
            'Access-Control-Allow-Methods': 'GET,POST,PUT,DELETE,OPTIONS',
            'Access-Control-Allow-Headers': 'Content-Type, Authorization, X-Requested-With, X-Worker-Secret',
            'Access-Control-Allow-Credentials': 'true',
            'Access-Control-Max-Age': '86400',
          },
        });
      }

      // Extract the action from the path, e.g., /login -> login
      const action = pathname.substring(1);

      // Preserve original search parameters from the frontend request
      const searchParams = new URLSearchParams(url.search);
      searchParams.set('action', action);

      // Construct the new URL to point directly to the backend API entry point.
      const backendUrl = new URL(`${backendHost}/backend/index.php?${searchParams.toString()}`);

      const newHeaders = new Headers(request.headers);
      newHeaders.set('Host', new URL(backendHost).hostname);
      // Add the worker secret to the request headers for backend verification.
      if (env.WORKER_SECRET) {
        newHeaders.set('X-Worker-Secret', env.WORKER_SECRET);
      }

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

        // **Enhanced Error Handling:** Check if the backend returned an HTML error page.
        const contentType = backendResp.headers.get('content-type') || '';
        if (!contentType.includes('application/json')) {
          // The backend returned a non-JSON response, likely an error page.
          // We return a structured JSON error to the frontend to prevent a crash.
          const errorBody = await backendResp.text();
          console.error(`Backend returned non-JSON response. Status: ${backendResp.status}. Body: ${errorBody.substring(0, 500)}`);
          const jsonError = { success: false, error: `Backend error: Received non-JSON response with status ${backendResp.status}.` };
          return new Response(JSON.stringify(jsonError), {
            status: 502, // Bad Gateway
            headers: {
              'Content-Type': 'application/json',
              'Access-Control-Allow-Origin': origin,
              'Access-Control-Allow-Credentials': 'true',
            },
          });
        }

      } catch (error) {
        console.error('Error proxying to backend:', error.message);
        return new Response(JSON.stringify({ success: false, error: 'API backend unavailable.' }), {
          status: 503, // Service Unavailable
          headers: {
            'Content-Type': 'application/json',
            'Access-Control-Allow-Origin': origin,
            'Access-Control-Allow-Credentials': 'true',
          },
        });
      }

      const respHeaders = new Headers(backendResp.headers);
      respHeaders.set('Access-Control-Allow-Origin', origin);
      respHeaders.set('Access-control-allow-credentials', 'true');

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
      let notFoundResponse = await env.ASSETS.fetch(new URL('/', url).toString());
      return new Response(notFoundResponse.body, {
        ...notFoundResponse,
        status: 200
      });
    }
  }
};