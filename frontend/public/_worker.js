export default {
  async fetch(request, env) {
    const url = new URL(request.url);
    const backendUrl = 'https://wenge.cloudns.ch';

    // A list of known API endpoint paths that should be proxied.
    const apiPaths = [
      '/get_numbers',
      '/check_session',
      '/login',
      '/logout',
      '/register',
      '/is_user_registered',
      '/email_upload',
      '/get_emails', // <-- I HAVE ADDED THIS
      '/tg_webhook'
    ];

    // Check if the request is for a known API endpoint.
    if (apiPaths.includes(url.pathname)) {
      // Extract the endpoint name from the path (e.g., '/get_numbers' -> 'get_numbers').
      const endpoint = url.pathname.substring(1);

      // Construct the new URL using the query string routing method.
      // e.g., https://wenge.cloudns.ch/backend/index.php?endpoint=get_numbers
      const newUrl = new URL(`${backendUrl}/backend/index.php?endpoint=${endpoint}`);

      // Append any existing search parameters from the original request.
      if (url.search) {
        newUrl.search += (newUrl.search ? '&' : '') + url.search.substring(1);
      }

      // Create a new request to the backend, preserving the original method, headers, and body.
      const newRequest = new Request(newUrl, {
        method: request.method,
        headers: request.headers,
        body: request.body,
        redirect: 'follow'
      });

      // Forward the request to the backend and return the response.
      return fetch(newRequest);
    }

    // For all other requests, let Cloudflare Pages serve the static assets.
    return env.ASSETS.fetch(request);
  },
};