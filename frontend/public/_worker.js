// frontend/public/_worker.js

export default {
  async fetch(request, env) {
    const url = new URL(request.url);
    const backendServer = "https://wenge.cloudns.ch"; // Your backend server

    // If the request is for a .php file, proxy it to the backend.
    if (url.pathname.endsWith('.php')) {
      const backendUrl = new URL(url.pathname, backendServer);
      backendUrl.search = url.search;

      // Create a new Headers object to ensure all headers, especially the
      // 'X-Telegram-Bot-Api-Secret-Token', are passed through correctly.
      const requestHeaders = new Headers(request.headers);

      const backendRequest = new Request(backendUrl.toString(), {
        method: request.method,
        headers: requestHeaders,
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
    return env.ASSETS.fetch(request);
  },
};