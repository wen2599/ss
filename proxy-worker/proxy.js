/**
 * Cloudflare Worker for API Proxy
 * Based on the enhanced template provided by the user.
 *
 * This worker proxies requests starting with /api/ to the backend server,
 * and lets Cloudflare Pages handle all other requests.
 */
export default {
  async fetch(request, env, ctx) {
    // The actual backend server's hostname
    const backendHost = 'https://wenge.cloudns.ch';
    const url = new URL(request.url);

    // --- 1. Handle API Proxy for paths starting with /api/ ---
    if (url.pathname.startsWith('/api/')) {

      // Handle CORS preflight (OPTIONS) requests
      if (request.method === 'OPTIONS') {
        return new Response(null, {
          status: 204,
          headers: {
            'Access-Control-Allow-Origin': '*',
            'Access-Control-Allow-Methods': 'GET, POST, PUT, DELETE, OPTIONS',
            'Access-Control-Allow-Headers': 'Content-Type, Authorization, X-Requested-With',
            'Access-Control-Max-Age': '86400',
          },
        });
      }

      // --- Forward the actual API request ---

      // Construct the backend URL.
      // We map the frontend's /api/ path to the backend's /backend/api/ path.
      // e.g., a request to /api/get_logs.php becomes https://wenge.cloudns.ch/backend/api/get_logs.php
      const backendPath = '/backend' + url.pathname; // Prepend /backend
      const backendUrl = `${backendHost}${backendPath}${url.search}`;

      // Copy headers from the original request, removing sensitive ones.
      const newHeaders = new Headers();
      for (const [key, value] of request.headers.entries()) {
        if (!/^host$/i.test(key) && !/^cf-/i.test(key) && !/^sec-/i.test(key)) {
          newHeaders.set(key, value);
        }
      }
      // Set the Host header to the backend's hostname
      newHeaders.set('Host', new URL(backendHost).hostname);

      const init = {
        method: request.method,
        headers: newHeaders,
        redirect: 'follow',
      };

      // Add the body for methods that have one
      if (request.method !== 'GET' && request.method !== 'HEAD') {
        init.body = await request.clone().arrayBuffer();
      }

      let backendResp;
      try {
        backendResp = await fetch(backendUrl, init);
      } catch (error) {
        console.error('Error proxying to backend:', error.message);
        return new Response('API backend unavailable.', { status: 502 });
      }

      // Create a new response, copying the backend response
      const respHeaders = new Headers(backendResp.headers);

      // Set our own permissive CORS headers for the frontend client
      respHeaders.set('Access-Control-Allow-Origin', '*');

      return new Response(backendResp.body, {
        status: backendResp.status,
        statusText: backendResp.statusText,
        headers: respHeaders,
      });
    }

    // --- 2. For all other paths, let Cloudflare Pages handle it ---
    // This serves the static frontend assets (HTML, JS, CSS).
    return env.ASSETS.fetch(request);
  }
};
