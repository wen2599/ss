export default {
  async fetch(request, env) {
    const url = new URL(request.url);

    if (url.pathname.startsWith('/api/')) {
      const backendUrl = 'https://wenge.cloudns.ch' + url.pathname + url.search;

      // Create a new Headers object, copying the original headers.
      const newHeaders = new Headers(request.headers);
      // Delete the Host header, so the fetch function will set it correctly based on the backendUrl.
      newHeaders.delete('Host');

      const requestOptions = {
          body: request.body,
          headers: newHeaders,
          method: request.method,
          redirect: 'follow',
      };

      // For requests with a body, we need to set duplex: 'half'.
      if (request.method === 'POST' || request.method === 'PUT' || request.method === 'PATCH' || request.method === 'DELETE') {
          requestOptions.duplex = 'half';
      }

      const backendRequest = new Request(backendUrl, requestOptions);

      return fetch(backendRequest);
    }

    // For non-API requests, serve the static assets.
    return env.ASSETS.fetch(request);
  }
};
