/**
 * Cloudflare Pages Function - Unified Worker
 *
 * This single worker handles two distinct functions:
 * 1. API Proxy: It proxies all fetch requests from the frontend starting with `/api/`
 *    to the backend PHP server, solving all CORS issues.
 * 2. Email Handler: It receives emails forwarded from Cloudflare Email Routing,
 *    extracts the chat log, and POSTs it to the backend for parsing and storage.
 */
export default {
  // --- 1. API Proxy Handler ---
  async fetch(request, env, ctx) {
    // The actual backend server's hostname, including the custom port
    const backendHost = 'https://wenge.cloudns.ch';
    const url = new URL(request.url);

    if (url.pathname.startsWith('/api/')) {
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

      // Construct the backend URL.
      // The path is forwarded directly (e.g., /api/get_logs.php -> https://wenge.cloudns.ch/api/get_logs.php)
      const backendUrl = `${backendHost}${url.pathname}${url.search}`;

      const newHeaders = new Headers(request.headers);
      newHeaders.set('Host', new URL(backendHost).hostname);

      const backendRequest = new Request(backendUrl, {
        method: request.method,
        headers: newHeaders,
        body: request.body,
        redirect: 'follow',
      });

      try {
        const backendResponse = await fetch(backendRequest);
        const respHeaders = new Headers(backendResponse.headers);
        respHeaders.set('Access-Control-Allow-Origin', '*');
        return new Response(backendResponse.body, {
          status: backendResponse.status,
          statusText: backendResponse.statusText,
          headers: respHeaders,
        });
      } catch (error) {
        console.error('Error proxying to backend:', error.message);
        return new Response('API backend unavailable.', { status: 502 });
      }
    }

    // For all other paths, serve static assets from Pages.
    return env.ASSETS.fetch(request);
  },

  // --- 2. Email Handler ---
  async email(message, env, ctx) {
    // API endpoints. These point to the public frontend domain to leverage the API proxy.
    const VERIFY_USER_URL = "https://ss.wenxiuxiu.eu.org/api/is_user_registered.php";
    const UPLOAD_API_URL = "https://ss.wenxiuxiu.eu.org/api/api.php";
    const WORKER_SECRET = "A_VERY_SECRET_KEY"; // This should ideally be a secret environment variable in a real app

    // --- 1. Extract Sender Email ---
    const senderEmail = message.from;
    if (!senderEmail) {
        console.error("Email received with no 'From' address.");
        return; // Stop processing
    }

    // --- 2. Verify if Sender is a Registered User ---
    try {
        const verificationUrl = `${VERIFY_USER_URL}?worker_secret=${WORKER_SECRET}&email=${encodeURIComponent(senderEmail)}`;
        const verificationResponse = await fetch(verificationUrl);
        const verificationData = await verificationResponse.json();

        if (!verificationResponse.ok || !verificationData.success || !verificationData.is_registered) {
            console.log(`Email from unregistered user '${senderEmail}' was rejected.`);
            return; // Stop processing if user is not registered
        }
    } catch (error) {
        console.error("Failed to verify user email:", error);
        return; // Stop processing on error
    }

    console.log(`Email from registered user '${senderEmail}' accepted. Proceeding to upload.`);

    // --- 3. Process and Upload Email Content ---
    const formData = new FormData();
    const rawEmail = await streamToString(message.raw);
    const textBodyMatch = rawEmail.match(/Content-Type: text\/plain;[\s\S]*?\r\n\r\n([\s\S]*)/);

    let chatContent = "Email did not contain a recognizable text part.";
    if (textBodyMatch && textBodyMatch[1]) {
      chatContent = textBodyMatch[1];
    }

    const blob = new Blob([chatContent], { type: 'text/plain' });
    const filename = `email-${message.headers.get("message-id") || new Date().toISOString()}.txt`;

    // Append all required fields for the backend API
    formData.append('chat_file', blob, filename);
    formData.append('worker_secret', WORKER_SECRET);
    formData.append('user_email', senderEmail);

    try {
      const uploadResponse = await fetch(UPLOAD_API_URL, {
        method: 'POST',
        body: formData,
      });
      if (!uploadResponse.ok) {
        const errorText = await uploadResponse.text();
        console.error(`Backend upload error: ${uploadResponse.status} ${uploadResponse.statusText}`, errorText);
      } else {
        console.log(`Successfully uploaded chat log for user ${senderEmail}.`);
      }
    } catch (error) {
      console.error("Failed to fetch upload API:", error);
    }
  },
};

// Helper function to convert a readable stream to a string
async function streamToString(stream) {
  const reader = stream.getReader();
  const decoder = new TextDecoder();
  let result = '';
  while (true) {
    const { done, value } = await reader.read();
    if (done) {
      break;
    }
    result += decoder.decode(value, { stream: true });
  }
  return result;
}
