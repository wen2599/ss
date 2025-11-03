export default {
  async fetch(request, env, ctx) {
    const url = new URL(request.url);

    if (url.pathname.startsWith('/api/')) {
      const backendUrl = `https://wenge.cloudns.ch${url.pathname}${url.search}`;
      
      const newRequest = new Request(backendUrl, request);

      newRequest.headers.set('Host', new URL(backendUrl).hostname);

      const response = await fetch(newRequest);
      
      const newResponse = new Response(response.body, response);
      newResponse.headers.set('Access-Control-Allow-Origin', url.origin);
      newResponse.headers.set('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS');
      newResponse.headers.set('Access-Control-Allow-Headers', 'Content-Type, Authorization');
      
      return newResponse;
    }

    return env.ASSETS.fetch(request);
  },
};