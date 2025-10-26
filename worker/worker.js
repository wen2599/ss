// worker/worker.js

export default {
  async fetch(request, env, ctx) {
    const url = new URL(request.url);
    const backendServer = env.PUBLIC_API_ENDPOINT || "https://wenge.cloudns.ch";

    // Only proxy API calls to the backend
    if (url.pathname.startsWith('/api/') || url.pathname.endsWith('.php')) {
        const backendUrl = new URL(url.pathname, backendServer);
        backendUrl.search = url.search;

        const newHeaders = new Headers(request.headers);
        newHeaders.delete('Host');

        const backendRequest = new Request(backendUrl, {
            method: request.method,
            headers: newHeaders,
            body: request.body,
            duplex: 'half'
        });

        return fetch(backendRequest);
    }

    // For all other requests, let Cloudflare Pages handle it.
    // This is the default behavior when the worker doesn't return a response.
    return env.ASSETS.fetch(request);
  },
};
