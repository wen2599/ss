export default {
  async fetch(request, env) {
    const url = new URL(request.url);
    const backendBase = 'https://wenge.cloudns.ch'; // Your Serv00 backend domain
    const backendUrl = new URL(backendBase);

    // Check if the request path starts with /api/
    if (url.pathname.startsWith('/api/')) {
      const newUrl = new URL(backendBase + url.pathname + url.search);

      // Handle preflight requests (OPTIONS)
      if (request.method === 'OPTIONS') {
        return handleOptions(request);
      }
      
      const newRequestHeaders = new Headers(request.headers);
      newRequestHeaders.set('Host', backendUrl.host);

      const newRequestInit = {
        method: request.method,
        headers: newRequestHeaders,
        redirect: 'follow'
      };

      // --- FIX: Conditionally add duplex: 'half' for requests with a body ---
      // Check if the request method typically involves a body (e.g., POST, PUT)
      const methodsWithBody = ['POST', 'PUT', 'PATCH'];
      if (methodsWithBody.includes(request.method.toUpperCase()) && request.body) {
        newRequestInit.body = request.body;
        newRequestInit.duplex = 'half'; // Required for streaming bodies
      }

      const newRequest = new Request(newUrl, newRequestInit);

      // Forward the request to the backend
      let response = await fetch(newRequest);

      // Clone the response because it's immutable
      response = new Response(response.body, response);

      // Add CORS headers to all responses
      addCORSHeaders(response);

      return response;
    }

    // For non-/api/ requests, serve the static assets
    return env.ASSETS.fetch(request);
  },
};

function addCORSHeaders(response) {
  response.headers.set('Access-Control-Allow-Origin', 'https://ss.wenxiuxiu.eu.org');
  response.headers.set('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS');
  response.headers.set('Access-Control-Allow-Headers', 'Content-Type, Authorization');
  response.headers.set('Access-Control-Allow-Credentials', 'true');
  response.headers.set('Access-Control-Max-Age', '86400');
}

function handleOptions(request) {
  const response = new Response(null, { status: 204 });
  addCORSHeaders(response);
  return response;
}
