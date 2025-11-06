export default {
  async fetch(request, env) {
    const url = new URL(request.url);

    // Check if the request is for our API
    if (url.pathname.startsWith('/api/')) {
      // Define your backend server URL
      const backendUrl = 'https://wenge.cloudns.ch';

      // Create a new URL for the backend request
      const newUrl = new URL(backendUrl + url.pathname + url.search);

      // Create a new request to the backend, copying method, headers, and body
      const backendRequest = new Request(newUrl, {
        method: request.method,
        headers: request.headers,
        body: request.body,
        redirect: 'follow',
      });

      // Forward the request to the backend and return the response
      return fetch(backendRequest);
    }

    // For all other requests, let Cloudflare Pages handle them (serves your React app)
    return env.fetch(request);
  },
};
