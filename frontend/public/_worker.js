export default {
  async fetch(request, env, ctx) {
    const url = new URL(request.url);
    const backendServer = env.PUBLIC_API_ENDPOINT || "https://wenge.cloudns.ch";

    if (url.pathname.startsWith('/api/')) {
      const backendUrl = new URL(url.pathname, backendServer);
      backendUrl.search = url.search;

      const backendRequest = new Request(backendUrl, {
        method: request.method,
        headers: request.headers,
        body: request.body,
        duplex: 'half',
      });

      return fetch(backendRequest);
    }

    // For non-API requests, you might want to serve your frontend assets here.
    // This example assumes Cloudflare Pages handles static asset serving automatically.
    // If you need to handle it yourself, you would add that logic here.
    return env.ASSETS.fetch(request);
  },
};
