export default {
  async fetch(request, env) {
    const url = new URL(request.url);

    if (url.pathname.startsWith('/api/')) {
      const backendUrl = 'https://wenge.cloudns.ch';

      // --- NEW ROUTING LOGIC ---
      // Extract the action from the path, e.g., /api/login -> login
      const action = url.pathname.substring('/api/'.length);

      // Preserve original search parameters
      const searchParams = new URLSearchParams(url.search);
      // Add our action parameter for the PHP router
      searchParams.set('action', action);

      // Construct the new URL to point to the single index.php endpoint
      const newUrl = new URL(`${backendUrl}/api/index.php?${searchParams.toString()}`);
      // --- END NEW ROUTING LOGIC ---

      const newHeaders = new Headers(request.headers);
      newHeaders.set('Host', new URL(backendUrl).host);

      const body = (request.method === 'POST' || request.method === 'PUT')
        ? await request.blob()
        : null;
      
      const newRequest = new Request(newUrl, {
        method: request.method,
        headers: newHeaders,
        body: body,
        redirect: 'follow'
      });

      try {
        const response = await fetch(newRequest);
        return response;
      } catch (e) {
        return new Response('Backend server is unavailable.', { status: 503 });
      }
    }

    return env.ASSETS.fetch(request);
  },
};
