// Define the backend API endpoint
const BACKEND_API_URL = 'https://wenxiuxiu.eu.org/api/api.php';

export default {
  async fetch(request, env, ctx) {
    const url = new URL(request.url);

    // Construct the full backend URL by appending the query string from the worker request
    const backendUrl = `${BACKEND_API_URL}${url.search}`;

    // Create a new request object to send to the backend.
    // This copies the method, headers, and body from the original request.
    const backendRequest = new Request(backendUrl, request);

    // Set the "Origin" header to match what the backend expects.
    // This is often a key part of solving CORS issues.
    backendRequest.headers.set('Origin', new URL(BACKEND_API_URL).origin);

    // Forward the request to the backend
    let backendResponse = await fetch(backendRequest);

    // Create a new response object based on the backend's response.
    // This is necessary to be able to modify the headers.
    let response = new Response(backendResponse.body, backendResponse);

    // --- Modify Response Headers ---
    // This is where we solve the CORS and cookie problems.

    // Allow requests from your frontend's origin
    response.headers.set('Access-Control-Allow-Origin', url.origin);

    // Allow credentials (cookies, auth headers) to be sent
    response.headers.set('Access-Control-Allow-Credentials', 'true');

    // Handle preflight (OPTIONS) requests
    if (request.method === 'OPTIONS') {
      response.headers.set('Access-Control-Allow-Methods', 'GET, POST, OPTIONS');
      response.headers.set('Access-Control-Allow-Headers', 'Content-Type, Authorization');
      return new Response(null, { headers: response.headers });
    }

    // Pass back the 'Set-Cookie' header from the backend if it exists.
    // This is crucial for the PHP session to work.
    const setCookieHeader = backendResponse.headers.get('Set-Cookie');
    if (setCookieHeader) {
      response.headers.set('Set-Cookie', setCookieHeader);
    }

    return response;
  },
};
