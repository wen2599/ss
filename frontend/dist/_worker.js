// frontend/public/_worker.js (MODIFIED FOR FRONT CONTROLLER)

export default {
  async fetch(request, env) {
    const url = new URL(request.url);
    const backendServer = "https://wenge.cloudns.ch"; // Your backend server

    // If the request is for index.php (our API gateway), proxy it.
    if (url.pathname === '/index.php') {
      const backendUrl = new URL(url.pathname, backendServer);
      backendUrl.search = url.search;

      const backendRequest = new Request(backendUrl.toString(), {
        method: request.method,
        headers: request.headers,
        body: request.body,
        redirect: 'follow',
        duplex: 'half',
      });

      try {
        return await fetch(backendRequest);
      } catch (error) {
        console.error(`Backend fetch failed: ${error.message}`);
        return new Response('Bad Gateway', { status: 502, statusText: 'Bad Gateway' });
      }
    }

    // Otherwise, serve the static assets from the Pages deployment.
    // This handles index.html, css, js, images, etc.
    return env.ASSETS.fetch(request);
  },
};
