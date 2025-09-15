export default {
  async fetch(request, env) {
    const url = new URL(request.url);
    const backendUrl = 'https://wenge.cloudns.ch';
    const apiPath = url.pathname;

    if (apiPath.startsWith('/api/')) {
      const newUrl = backendUrl + apiPath + url.search;

      const newRequestInit = {
        method: request.method,
        headers: new Headers(request.headers),
        body: request.body,
        redirect: request.redirect
      };

      newRequestInit.headers.set('Host', new URL(backendUrl).host);
      newRequestInit.headers.set('Origin', new URL(backendUrl).origin);

      const newRequest = new Request(newUrl, newRequestInit);

      return fetch(newRequest);
    }

    // In a real Cloudflare Pages setup, the request would fall through to the static assets.
    // Returning a 404 here is a fallback for requests that don't match /api/ and are not static assets.
    return new Response('Not an API request. This should be handled by static assets.', { status: 404 });
  }
};
