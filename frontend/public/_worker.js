export default {
  async fetch(request, env, ctx) {
    const url = new URL(request.url);
    const backendServer = env.PUBLIC_API_ENDPOINT || "https://wenge.cloudns.ch";

    if (url.pathname.startsWith('/api/')) {
      const backendUrl = new URL(url.pathname, backendServer);
      backendUrl.search = url.search;

      const newHeaders = new Headers(request.headers);
      newHeaders.delete('host'); // VERY IMPORTANT: Remove the original host header

      const requestOptions = {
        method: request.method,
        headers: newHeaders,
      };

      // Only include body and duplex if a body is present
      if (request.body) {
        requestOptions.body = request.body;
        requestOptions.duplex = 'half';
      }

      const backendRequest = new Request(backendUrl, requestOptions);

      return fetch(backendRequest);
    }

    // For non-API requests, you might want to serve your frontend assets here.
    // This example assumes Cloudflare Pages handles static asset serving automatically.
    // If you need to handle it yourself, you would add that logic here.
    return env.ASSETS.fetch(request);
  },
};
