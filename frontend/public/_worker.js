export default {
  async fetch(request, env) {
    const url = new URL(request.url);

    // We only proxy requests to the /api/ path
    if (url.pathname.startsWith('/api/')) {
      // Your backend API address
      const backendUrl = 'https://wenge.cloudns.ch';

      // Construct the new URL to point to the backend
      const newUrl = new URL(backendUrl + url.pathname + url.search);

      // Create a new headers object from the original request
      const newHeaders = new Headers(request.headers);
      // Set the Host header to match the backend, which is crucial for some server configs
      newHeaders.set('Host', new URL(backendUrl).host);

      // Buffer the body for POST/PUT requests to ensure stable forwarding
      const body = (request.method === 'POST' || request.method === 'PUT')
        ? await request.blob()
        : null;
      
      // Create a new request object to forward
      const newRequest = new Request(newUrl, {
        method: request.method,
        headers: newHeaders,
        body: body, // Pass the buffered body
        redirect: 'follow'
      });

      try {
        // Make the request to the backend server
        const response = await fetch(newRequest);
        return response;
      } catch (e) {
        // If the backend is unreachable, return a more specific error
        return new Response('Backend server is unavailable.', { status: 503 });
      }
    }

    // For all other requests, serve the static assets from Pages
    return env.ASSETS.fetch(request);
  },
};
