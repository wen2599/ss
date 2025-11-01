export default {
  async fetch(request, env) {
    const url = new URL(request.url);
    const backendBase = 'https://wenge.cloudns.ch'; // Your Serv00 backend domain
    const backendUrl = new URL(backendBase);

    // Check if the request path starts with /api/
    if (url.pathname.startsWith('/api/')) {
      // --- ROUTING LOGIC: Rewrite the path to use the new api_router.php ---
      // Original path, e.g., /api/auth.php
      const originalPath = url.pathname; 
      // Original query, e.g., ?action=login
      const originalQuery = url.search;
      // New path for the router
      const routerPath = '/api_router.php';
      
      // Construct new query parameters
      const newParams = new URLSearchParams();
      newParams.append('path', originalPath); // Pass the original path as a 'path' parameter
      
      // Append original query parameters to the new ones
      if (originalQuery) {
          new URLSearchParams(originalQuery.substring(1)).forEach((value, key) => {
              newParams.append(key, value);
          });
      }
      
      const newUrl = new URL(backendBase + routerPath + '?' + newParams.toString());
      
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

      if (request.body) {
        newRequestInit.body = request.body;
        newRequestInit.duplex = 'half';
      }

      const newRequest = new Request(newUrl, newRequestInit);
      let response = await fetch(newRequest);
      response = new Response(response.body, response);
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
