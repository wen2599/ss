/**
 * @file Cloudflare Worker script for routing and proxying requests.
 *
 * This worker acts as the entry point for the application. It serves static assets
 * from Cloudflare Pages and proxies all API requests to a configurable backend.
 *
 * Required Environment Variables:
 * - ASSETS: The binding to the Cloudflare Pages static asset handler.
 * - BACKEND_HOST: The full URL of the backend API (e.g., 'https://api.example.com').
 * - WORKER_SECRET: A secret token shared with the backend for verification.
 */

/**
 * Handles incoming API requests by proxying them to the backend.
 * @param {Request} request The original incoming request.
 * @param {object} env The worker's environment variables.
 * @returns {Promise<Response>} The response from the backend or a generated error response.
 */
async function handleApiRequest(request, env) {
  const { BACKEND_HOST, WORKER_SECRET } = env;
  const url = new URL(request.url);
  const origin = request.headers.get('Origin');

  // Handle CORS pre-flight requests
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

  // Construct the backend URL
  const action = url.pathname.substring(1);
  const searchParams = new URLSearchParams(url.search);
  searchParams.set('action', action);
  const backendUrl = new URL(`${BACKEND_HOST}/backend/index.php?${searchParams.toString()}`);

  // Prepare the request to the backend
  const newHeaders = new Headers(request.headers);
  newHeaders.set('Host', new URL(BACKEND_HOST).hostname);
  if (WORKER_SECRET) {
    newHeaders.set('X-Worker-Secret', WORKER_SECRET);
  }

  const init = {
    method: request.method,
    headers: newHeaders,
    redirect: 'follow',
  };
  if (request.method !== 'GET' && request.method !== 'HEAD') {
    init.body = request.body;
  }

  try {
    const backendResp = await fetch(backendUrl, init);

    // Check if the backend returned a non-JSON response (e.g., an HTML error page)
    const contentType = backendResp.headers.get('content-type') || '';
    if (!contentType.includes('application/json')) {
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

    // Proxy the response back to the client, ensuring CORS headers are set
    const respHeaders = new Headers(backendResp.headers);
    respHeaders.set('Access-Control-Allow-Origin', origin);
    respHeaders.set('Access-Control-Allow-Credentials', 'true');

    return new Response(backendResp.body, {
      status: backendResp.status,
      statusText: backendResp.statusText,
      headers: respHeaders,
    });

  } catch (error) {
    console.error('Error proxying to backend:', error.message);
    const jsonError = { success: false, error: 'API backend unavailable.' };
    return new Response(JSON.stringify(jsonError), {
      status: 503, // Service Unavailable
      headers: {
        'Content-Type': 'application/json',
        'Access-Control-Allow-Origin': origin,
        'Access-Control-Allow-Credentials': 'true',
      },
    });
  }
}

/**
 * Handles requests for static assets by fetching them from the ASSETS binding.
 * @param {Request} request The original incoming request.
 * @param {object} env The worker's environment variables.
 * @returns {Promise<Response>} The fetched static asset or a fallback to the root page.
 */
async function handleStaticAsset(request, env) {
  try {
    return await env.ASSETS.fetch(request);
  } catch (e) {
    // If an asset is not found, serve the main index.html to support client-side routing.
    const url = new URL(request.url);
    const notFoundResponse = await env.ASSETS.fetch(new URL('/', url).toString());
    return new Response(notFoundResponse.body, {
      ...notFoundResponse,
      status: 200,
    });
  }
}


export default {
  async fetch(request, env, ctx) {
    // Check for required environment variables
    if (!env.BACKEND_HOST || !env.WORKER_SECRET) {
        console.error("CRITICAL: BACKEND_HOST and WORKER_SECRET environment variables must be set.");
        return new Response("Worker is not configured correctly. Missing environment variables.", { status: 500 });
    }

    const { pathname } = new URL(request.url);

    // Whitelist of all known API routes
    const apiRoutes = [
      '/check_session', '/email_upload', '/get_bills', '/delete_bill',
      '/get_game_data', '/get_lottery_results', '/is_user_registered',
      '/login', '/logout', '/process_text', '/register', '/update_settlement'
    ];

    // Route the request to the appropriate handler
    if (apiRoutes.includes(pathname)) {
      return handleApiRequest(request, env);
    } else {
      return handleStaticAsset(request, env);
    }
  },
};