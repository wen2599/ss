// The backend server's URL
const BACKEND_URL = 'https://wenge.cloudns.ch';

// A list of all API routes that should be proxied to the backend
const API_ROUTES = [
  '/register',
  '/login',
  '/logout',
  '/check_session',
  '/process_email',
];

// The secret key that must match the one in the backend's .env file
const WORKER_SECRET = 'a_very_secret_string_that_should_be_changed';

export default {
  async fetch(request, env, ctx) {
    const url = new URL(request.url);
    const pathname = url.pathname;

    // Handle CORS preflight requests
    if (request.method === 'OPTIONS') {
      return handleOptions(request);
    }

    // Check if the request path is an API route
    if (API_ROUTES.some(route => pathname.startsWith(route))) {
      // Construct the full backend URL
      const backendUrl = new URL(pathname + url.search, BACKEND_URL);

      // Create a new request to forward to the backend, copying headers and body
      const backendRequest = new Request(backendUrl, request);

      // Add the crucial security header
      backendRequest.headers.set('X-Worker-Secret', WORKER_SECRET);

      // Ensure the Host header is correct for the backend server
      backendRequest.headers.set('Host', new URL(BACKEND_URL).host);

      try {
        // Forward the request and receive the response
        const response = await fetch(backendRequest);

        // Create a new response to be able to modify headers
        const newResponse = new Response(response.body, response);

        // Set CORS headers on the response to allow the frontend to read it
        newResponse.headers.set('Access-Control-Allow-Origin', url.origin);
        newResponse.headers.set('Access-Control-Allow-Credentials', 'true');

        return newResponse;
      } catch (e) {
        return new Response(`Error proxying to backend: ${e.message}`, { status: 502 });
      }
    }

    // For any other requests, serve the static assets from Cloudflare Pages
    return env.ASSETS.fetch(request);
  },
};

// Handles CORS preflight OPTIONS requests
function handleOptions(request) {
  let headers = request.headers;
  if (
    headers.get("Origin") !== null &&
    headers.get("Access-Control-Request-Method") !== null &&
    headers.get("Access-Control-Request-Headers") !== null
  ) {
    // This is a valid preflight request, return appropriate CORS headers
    return new Response(null, {
      headers: {
        "Access-Control-Allow-Origin": "*", // Be more restrictive in production
        "Access-Control-Allow-Methods": "POST, GET, OPTIONS",
        "Access-Control-Allow-Headers": "Content-Type, Authorization, X-Worker-Secret",
        "Access-Control-Allow-Credentials": "true",
      },
    });
  } else {
    // This is a standard OPTIONS request
    return new Response(null, {
      headers: {
        "Allow": "POST, GET, OPTIONS",
      },
    });
  }
}