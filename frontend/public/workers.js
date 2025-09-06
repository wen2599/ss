const BACKEND_URL = 'https://wenge.cloudns.ch';

self.addEventListener('fetch', (event) => {
  const url = new URL(event.request.url);

  // Only proxy requests to our designated API path
  if (url.pathname.startsWith('/api/')) {
    // Create the full backend URL
    const backendApiUrl = BACKEND_URL + url.pathname + url.search;

    // Create a new request object to avoid issues with reusing the original request
    const newRequest = new Request(backendApiUrl, {
        method: event.request.method,
        headers: event.request.headers,
        body: event.request.body,
        mode: 'cors', // Important for cross-origin requests
        redirect: 'follow'
    });

    event.respondWith(
      fetch(newRequest)
        .then((response) => {
          // Create a new response to be able to modify headers
          const modifiedHeaders = new Headers(response.headers);
          // Set the CORS header to allow any origin
          modifiedHeaders.set('Access-Control-Allow-Origin', '*');
          modifiedHeaders.set('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS');
          modifiedHeaders.set('Access-Control-Allow-Headers', 'Content-Type, Authorization');

          const newResponse = new Response(response.body, {
            status: response.status,
            statusText: response.statusText,
            headers: modifiedHeaders,
          });

          return newResponse;
        })
        .catch((error) => {
          console.error('Service Worker fetch error:', error);
          return new Response(`Service Worker failed to fetch: ${error}`, { status: 500 });
        })
    );
  }
  // For requests not matching the API path, do nothing and let the browser handle it normally.
});

self.addEventListener('install', (event) => {
  // Perform install steps
  console.log('Service Worker installing.');
});

self.addEventListener('activate', (event) => {
  // Perform activate steps
  console.log('Service Worker activating.');
});
