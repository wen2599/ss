
export default {
  async fetch(request, env) {
    const url = new URL(request.url);

    // Check if it's an API request
    if (url.pathname.startsWith('/api/')) {
      // Extract the endpoint name from the path
      // e.g., /api/chart-data -> chart-data
      const endpoint = url.pathname.substring(5); 

      // Construct the new backend URL with the endpoint as a query parameter
      const backendUrl = new URL('https://wenge.cloudns.ch/index.php');
      backendUrl.searchParams.append('endpoint', endpoint);

      // If the original request had search params, forward them too.
      // This is useful for GET requests with parameters, e.g., /api/users?id=123
      if (url.search) {
          const originalParams = new URLSearchParams(url.search);
          for (const [key, value] of originalParams) {
              backendUrl.searchParams.append(key, value);
          }
      }

      // Create a new request to the backend
      const backendRequest = new Request(backendUrl.toString(), {
        method: request.method,
        headers: request.headers,
        body: request.body,
        redirect: 'follow'
      });

      // Set the Host header
      backendRequest.headers.set('Host', backendUrl.hostname);

      // Fetch the response from the backend
      const response = await fetch(backendRequest);

      // Create a new Headers object to make it mutable
      const newHeaders = new Headers(response.headers);

      // Add CORS headers
      newHeaders.set('Access-Control-Allow-Origin', 'https://ss.wenxiuxiu.eu.org');
      newHeaders.set('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS');
      newHeaders.set('Access-Control-Allow-Headers', 'Content-Type, Authorization');

      // Handle OPTIONS preflight requests
      if (request.method === 'OPTIONS') {
        return new Response(null, {
          status: 204,
          headers: newHeaders
        });
      }
      
      return new Response(response.body, {
        status: response.status,
        statusText: response.statusText,
        headers: newHeaders
      });

    } else {
      // For non-API requests, serve the static assets
      return env.ASSETS.fetch(request);
    }
  },
};
