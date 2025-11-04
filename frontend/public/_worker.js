export default {
  async fetch(request, env, ctx) {
    const url = new URL(request.url);

    // API proxy
    if (url.pathname.startsWith('/api/')) {
      const targetUrl = new URL('https://wenge.cloudns.ch/' + url.pathname.substring(5) + url.search);
      
      const headers = new Headers(request.headers);
      headers.set('X-API-KEY', env.API_KEY);
      
      const newRequest = new Request(targetUrl, {
        method: request.method,
        headers: headers,
        body: request.body,
        redirect: 'follow'
      });

      return fetch(newRequest);
    }

    // Telegram Bot webhook proxy
    if (url.pathname === '/webhook') {
      const targetUrl = new URL('https://wenge.cloudns.ch/webhook');
      
      const newRequest = new Request(targetUrl, request);
      
      return fetch(newRequest);
    }

    // Serve the static assets from the Pages deployment
    return env.ASSETS.fetch(request);
  },
};
