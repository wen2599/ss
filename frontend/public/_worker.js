// frontend/public/_worker.js

export default {
  async fetch(request, env) {
    const url = new URL(request.url);

    // Get the backend hostname from the custom environment variable
    // Fallback to the default production URL if not set
    const backendHost = env.BACKEND_HOST || 'wenge.cloudns.ch';

    // Construct the backend URL
    const backendUrl = new URL(url.pathname + url.search, `https://${backendHost}`);

    // Create a new headers object from the original request's headers
    const newHeaders = new Headers(request.headers);

    // Set the 'Host' header to match the backend's hostname
    // This is crucial for the backend server to correctly route the request
    newHeaders.set('Host', backendHost);

    // Create a new request to forward to the backend
    const backendRequest = new Request(backendUrl, {
      method: request.method,
      headers: newHeaders,
      body: request.body,
      redirect: 'follow',
      // The 'duplex' property is required for streaming request bodies
      ...(request.body && { duplex: 'half' }),
      // Add cache control to force the worker to always connect to the backend
      // and bypass any cached error responses.
      cf: {
        cacheTtl: 0
      }
    });

    try {
      // Fetch the response from the backend
      const response = await fetch(backendRequest);

      // If the backend returns an error (e.g., 500), we pass it through
      // This allows us to see the actual PHP error in the browser instead of a generic 502
      if (!response.ok) {
        return new Response(response.body, {
          status: response.status,
          statusText: response.statusText,
          headers: response.headers,
        });
      }

      // If the response is successful, return it directly
      return response;

    } catch (error) {
      console.error('Cloudflare Worker fetch error:', error);
      // This error is for when the worker can't even reach the backend
      return new Response(`Worker Error: Could not connect to backend. ${error.message}`, { status: 502 });
    }
  },
};
