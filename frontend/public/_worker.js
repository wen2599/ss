export default {
  async fetch(request, env) {
    const url = new URL(request.url);
    const backendUrl = 'https://wenge.cloudns.ch';

    // Define the list of known API endpoints. These paths will be proxied to the backend.
    const apiPaths = [
      '/get_numbers',
      '/check_session',
      '/login',
      '/logout',
      '/register',
      '/is_user_registered',
      '/email_upload'
    ];

    // Check if the requested path is an API endpoint.
    if (apiPaths.includes(url.pathname)) {
      // Construct the new URL for the backend.
      // e.g., https://wenge.cloudns.ch/backend/get_numbers
      const newUrl = new URL(`${backendUrl}/backend${url.pathname}${url.search}`);

      // Create a new request to the backend, preserving method, headers, and body.
      const newRequest = new Request(newUrl, {
        method: request.method,
        headers: request.headers,
        body: request.body,
        redirect: 'follow'
      });

      // Forward the request and return the response.
      return fetch(newRequest);
    }

    // For all other requests, serve static assets from Cloudflare Pages.
    return env.ASSETS.fetch(request);
  },
};