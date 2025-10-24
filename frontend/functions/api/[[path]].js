/**
 * This function acts as a proxy for all API requests.
 * It intercepts any request made to /api/* and forwards it to the backend server.
 */
export async function onRequest(context) {
  // The original request URL, e.g., https://ss.wenxiuxiu.eu.org/api/register
  const url = new URL(context.request.url);

  // The path of the request, e.g., /api/register
  const path = url.pathname;

  // The backend server where the actual API lives
  const backendServer = 'https://wenge.cloudns.ch';

  // Construct the full URL for the backend request
  // It will be, for example, https://wenge.cloudns.ch/api/register
  const backendUrl = new URL(path + url.search, backendServer);

  // Create a new request to the backend.
  // It's crucial to preserve the original method, headers, and body.
  const backendRequest = new Request(backendUrl, {
    method: context.request.method,
    headers: context.request.headers,
    body: context.request.body,
    duplex: 'half', // Required for streaming request bodies (e.g., POST)
  });

  // Forward the request to the backend and return the response directly to the user.
  return fetch(backendRequest);
}
