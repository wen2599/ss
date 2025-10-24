export default {
  async fetch(request, env) {
    const url = new URL(request.url);

    // Check if the request is for our API proxy
    if (url.pathname.startsWith('/api/')) {
      // Create a new URL object for the backend target
      const backendUrl = new URL(url.pathname, 'https://wenge.cloudns.ch');
      
      // Preserve the search parameters from the original request
      backendUrl.search = url.search;

      // Create a new request to the backend.
      // This new request will have the correct backend URL but preserves the method,
      // headers, and body from the original incoming request.
      const backendRequest = new Request(backendUrl, {
        method: request.method,
        headers: request.headers,
        body: request.body,
        redirect: 'follow'
      });

      // Make the actual request to the backend server and return its response
      return fetch(backendRequest);
    }

    // For any request that doesn't start with /api/, serve the static assets
    // from the Cloudflare Pages build. This is the default behavior.
    return env.ASSETS.fetch(request);
  }
}
