export default {
  async fetch(request, env) {
    const url = new URL(request.url);

    // Check if it's an API request
    if (url.pathname.startsWith('/api/')) {
      // Construct the backend URL by just replacing the hostname
      const backendUrl = new URL(url);
      backendUrl.hostname = 'wenge.cloudns.ch';
      backendUrl.protocol = 'https';

      // Create a new request to the backend, preserving the original path and query
      const backendRequest = new Request(backendUrl.toString(), {
        method: request.method,
        headers: request.headers,
        body: request.body,
        redirect: 'follow'
      });

      // It's often important to set the Host header to the backend's hostname
      backendRequest.headers.set('Host', backendUrl.hostname);

      try {
        // Fetch the response from the backend
        const backendResponse = await fetch(backendRequest);

        // Create a new mutable Headers object from the backend response
        const newHeaders = new Headers(backendResponse.headers);

        // Add CORS headers to allow the frontend to access the API
        // This allows your frontend at https://ss.wenxiuxiu.eu.org to make requests
        newHeaders.set('Access-Control-Allow-Origin', 'https://ss.wenxiuxiu.eu.org');
        newHeaders.set('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS');
        newHeaders.set('Access-Control-Allow-Headers', 'Content-Type, Authorization');
        
        // Return the backend's response with the added CORS headers
        return new Response(backendResponse.body, {
          status: backendResponse.status,
          statusText: backendResponse.statusText,
          headers: newHeaders
        });

      } catch (error) {
        // If there's an error fetching from the backend, return a descriptive error
        return new Response(`Error proxying to backend: ${error.message}`, { status: 502 });
      }
    }

    // For non-API requests, serve the static assets from Cloudflare Pages.
    // This is crucial for your React app to load correctly.
    return env.ASSETS.fetch(request);
  },
};
