// frontend/public/_worker.js

export default {
  async fetch(request, env) {
    const url = new URL(request.url);
    const backendServer = "https://wenge.cloudns.ch"; // Your backend server

    // If the request is for a .php file, proxy it to the backend.
    if (url.pathname.endsWith('.php')) {
      const backendUrl = new URL(url.pathname, backendServer);
      backendUrl.search = url.search;

      const backendRequest = new Request(backendUrl.toString(), {
        method: request.method,
        headers: request.headers,
        body: request.body,
        redirect: 'follow',
        duplex: 'half', // Required for streaming bodies in POST requests
      });

      try {
        return await fetch(backendRequest);
      } catch (error) {
        console.error(`Backend fetch failed: ${error.message}`);
        return new Response('Bad Gateway', { status: 502, statusText: 'Bad Gateway' });
      }
    }

    // Otherwise, serve the static assets from the Pages deployment.
    return env.ASSETS.fetch(request);
  },
};