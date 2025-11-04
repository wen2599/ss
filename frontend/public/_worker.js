export default {
  async fetch(request, env, ctx) {
    const url = new URL(request.url);
    const backendUrl = "https://wenge.cloudns.ch";

    if (url.pathname.startsWith('/api/')) {
      const newPath = "/index.php" + url.search;
      const backendApiUrl = new URL(newPath, backendUrl);
      
      const newRequest = new Request(backendApiUrl, {
        method: request.method,
        headers: request.headers,
        body: request.body,
        duplex: 'half',   // <-- 确认这一行存在！
      });

      newRequest.headers.set('Host', new URL(backendUrl).hostname);
      return fetch(newRequest);
    }

    return env.ASSETS.fetch(request);
  },
};