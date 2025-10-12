// frontend/public/_worker.js

export default {
  async fetch(request, env) {
    const url = new URL(request.url);

    // If the request is for an API endpoint, proxy it to the backend.
    if (url.pathname.startsWith('/api/')) {
      // Create a new URL to the backend server.
      const backendUrl = new URL(request.url);
      backendUrl.hostname = 'wenge.cloudns.ch';
      backendUrl.protocol = 'https';

      // Create a new request to the backend.
      const backendRequest = new Request(backendUrl, request);
      
      // Set the Host header to match the backend hostname.
      // This is crucial for many shared hosting environments.
      backendRequest.headers.set('Host', backendUrl.hostname);
      
      // Fetch and return the response from the backend.
      return fetch(backendRequest);
    }

    // Otherwise, serve the static assets from the Pages build.
    return env.ASSETS.fetch(request);
  },
};
