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
    });

    try {
      // Forward the request to the backend and return the response
      return await fetch(backendRequest);
    } catch (error) {
      console.error('Error fetching from backend:', error);
      return new Response('Error connecting to the backend.', { status: 502 });
    }
  },
};
