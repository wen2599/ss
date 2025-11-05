export default {
  async fetch(request, env) {
    let url = new URL(request.url);
    if (url.pathname.startsWith('/api/')) {
      // Proxy API requests to the backend
      return fetch(`https://your-backend.com${url.pathname}`, request);
    }
    // Serve static assets
    return env.ASSETS.fetch(request);
  },
};