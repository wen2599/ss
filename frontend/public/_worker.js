export default {
  async fetch(request, env) {
    const url = new URL(request.url);

    if (url.pathname.startsWith('/api/')) {
      const backendUrl = 'https://wenge.cloudns.ch';
      const action = url.pathname.substring('/api/'.length);
      const searchParams = new URLSearchParams(url.search);
      searchParams.set('action', action);

      const newUrl = new URL(`${backendUrl}/api/index.php?${searchParams.toString()}`);

      // --- NEW MINIMAL HEADERS LOGIC ---
      // Create a new Headers object instead of cloning, to avoid forwarding
      // potentially problematic headers like 'sec-fetch-...' etc.
      const newHeaders = new Headers();
      newHeaders.set('Host', new URL(backendUrl).host);

      // Only forward essential headers
      if (request.headers.has('Content-Type')) {
        newHeaders.set('Content-Type', request.headers.get('Content-Type'));
      }
      if (request.headers.has('Authorization')) {
        newHeaders.set('Authorization', request.headers.get('Authorization'));
      }
      // --- END NEW MINIMAL HEADERS LOGIC ---

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
