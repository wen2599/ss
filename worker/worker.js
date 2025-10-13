/**
 * Welcome to Cloudflare Workers!
 *
 * This worker serves two main purposes:
 * 1. As a reverse proxy, it forwards API requests from the frontend to the backend PHP server.
 * 2. As an email handler, it processes incoming emails and forwards them to the backend.
 */

import PostalMime from 'postal-mime';

// --- Utility Functions for Email Parsing ---
async function parseMime(mimeStream) {
    const reader = mimeStream.getReader();
    const chunks = [];
    while (true) {
        const { done, value } = await reader.read();
        if (done) break;
        chunks.push(value);
    }

    const rawEmail = new TextDecoder("utf-8").decode(new Uint8Array(chunks.reduce((acc, chunk) => acc.concat(Array.from(chunk)), [])));
    const parser = new PostalMime();
    return await parser.parse(rawEmail);
}


// --- Worker Entry Point ---
export default {
  /**
   * Handles HTTP requests, acting as a proxy for the backend API.
   */
  async fetch(request, env, ctx) {
    const url = new URL(request.url);
    const backendServer = env.PUBLIC_API_ENDPOINT || "https://wenge.cloudns.ch";

    // If the request is for a .php file, proxy it to the backend.
    if (url.pathname.endsWith('.php')) {
      const backendUrl = new URL(url.pathname, backendServer);
      backendUrl.search = url.search;

      let headers = new Headers(request.headers);
      const requestedEndpoint = url.pathname.substring(1); // e.g., 'telegramWebhook.php'

      // Add the Telegram secret token if the request is for the Telegram webhook.
      if (requestedEndpoint === 'telegramWebhook.php' && env.TELEGRAM_WEBHOOK_SECRET) {
        headers.set('X-Telegram-Bot-Api-Secret-Token', env.TELEGRAM_WEBHOOK_SECRET);
      }

      // Add the Email Handler secret token if the request is for email uploads.
      // This makes the proxy secure for any future client-side calls.
      if (requestedEndpoint === 'email_upload' && env.EMAIL_HANDLER_SECRET) {
        headers.set('X-Email-Handler-Secret-Token', env.EMAIL_HANDLER_SECRET);
      }

      const backendRequest = new Request(backendUrl, {
        method: request.method,
        headers: headers, 
        body: request.body,
        redirect: 'follow',
        duplex: 'half',
      });

      // Forward the request to the backend and return the response.
      return fetch(backendRequest);
    }

    // For any other requests, serve the static assets.
    return env.ASSETS.fetch(request);
  },

  /**
   * Handles incoming emails routed by Cloudflare Email Routing.
   */
  async email(message, env, ctx) {
    const { PUBLIC_API_ENDPOINT, EMAIL_HANDLER_SECRET } = env;
    if (!PUBLIC_API_ENDPOINT || !EMAIL_HANDLER_SECRET) {
      console.error("Missing required environment variables for email processing.");
      return; // Stop processing if configuration is missing.
    }

    let parsedEmail;
    try {
        parsedEmail = await parseMime(message.raw);
    } catch (e) {
        console.error('Error parsing email mime:', e);
        return;
    }

    // The backend endpoint for receiving email data.
    const uploadUrl = `${PUBLIC_API_ENDPOINT}/api/email_upload`;

    try {
      // Send the parsed email data directly to the backend.
      const response = await fetch(uploadUrl, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          // Include the secret token to authenticate with the backend.
          'X-Email-Handler-Secret-Token': EMAIL_HANDLER_SECRET
        },
        body: JSON.stringify({
          from: message.from,
          to: message.to,
          subject: message.headers.get('subject') || '',
          body: parsedEmail.text || '', 
          html: parsedEmail.html || ''
        })
      });

      if (!response.ok) {
          const errorText = await response.text();
          console.error(`Error forwarding email to backend: ${response.status} ${response.statusText}`, errorText);
      }

    } catch (error) {
      console.error('Fetch error when forwarding email to backend:', error);
    }
  }
};
