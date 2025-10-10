/**
 * Welcome to Cloudflare Workers!
 *
 * This is a template for a Scheduled Worker:
 * - Run on a schedule
 * - Use KV for persistent storage
 * - Send email alerts using MailChannels
 *
 * Resources:
 * - https://developers.cloudflare.com/workers/
 * - https://developers.cloudflare.com/workers/runtime-apis/kv/
 * - https://mailchannels.zendesk.com/hc/en-us/articles/4413695934349-Sending-Email-from-Cloudflare-Workers-using-the-MailChannels-Send-API
 */

import PostalMime from 'postal-mime';

// --- Utility Functions for Email Parsing ---

/**
 * Parses the raw MIME content of an email.
 * @param {ReadableStream} mimeStream - The raw email content stream.
 * @returns {Promise<object>} - A promise that resolves to the parsed email object.
 */
async function parseMime(mimeStream) {
    const reader = mimeStream.getReader();
    const chunks = [];
    let done, value;
    while (!({ done, value } = await reader.read()), done) {
        chunks.push(value);
    }

    // Combine chunks into a single Uint8Array
    const totalLength = chunks.reduce((acc, chunk) => acc + chunk.length, 0);
    const combinedChunks = new Uint8Array(totalLength);
    let offset = 0;
    for (const chunk of chunks) {
        combinedChunks.set(chunk, offset);
        offset += chunk.length;
    }

    // Create a string from the Uint8Array
    const rawEmail = new TextDecoder("utf-8").decode(combinedChunks);

    // Use postal-mime to parse the email
    const parser = new PostalMime();
    return await parser.parse(rawEmail);
}


// --- Worker Entry Point ---
export default {
  /**
   * Rewritten fetch handler to proxy API requests to the backend.
   * - Any path matching an endpoint in apiEndpoints will be proxied.
   * - All other requests will be treated as static assets.
   */
  async fetch(request, env, ctx) {
    const url = new URL(request.url);
    const backendServer = env.PUBLIC_API_ENDPOINT || "https://wenge.cloudns.ch";

    const apiEndpoints = [
      'check_session', 'login', 'logout', 'register',
      'get_numbers', 'get_bills', 'get_emails',
      'is_user_registered', 'email_upload',
      'telegram_webhook'
    ];

    let requestedEndpoint = url.pathname.startsWith('/api/')
      ? url.pathname.substring(5)
      : url.pathname.substring(1);

    if (apiEndpoints.includes(requestedEndpoint)) {
      const backendUrl = `${backendServer}/index.php?endpoint=${requestedEndpoint}${url.search}`;

      // Append the secret if this is a telegram webhook
      let headers = new Headers(request.headers);
      if (requestedEndpoint === 'telegram_webhook') {
         // Note: We are creating a new headers object to avoid modifying the original request headers.
         if (env.TELEGRAM_WEBHOOK_SECRET) {
            headers.set('X-Telegram-Bot-Api-Secret-Token', env.TELEGRAM_WEBHOOK_SECRET);
         }
      }

      const backendRequest = new Request(backendUrl, {
        method: request.method,
        headers: headers, // Use the new headers object
        body: request.body,
        redirect: 'follow'
      });

      return fetch(backendRequest);
    }

    // For any other request, serve from Cloudflare Pages assets.
    return env.ASSETS.fetch(request);
  },

  /**
   * Handles incoming emails routed by Cloudflare Email Routing.
   * - Verifies the sender is a registered user.
   * - Parses the email content.
   * - Forwards the parsed content to the backend API for storage.
   */
  async email(message, env, ctx) {
    // Environment variables required for this function to work.
    const { WORKER_SECRET, PUBLIC_API_ENDPOINT } = env;
    if (!WORKER_SECRET || !PUBLIC_API_ENDPOINT) {
      // Silently fail if essential configuration is missing.
      return;
    }

    const senderEmail = message.from;

    // 1. Verify if the sender is a registered user in our system.
    try {
      const verificationUrl = `${PUBLIC_API_ENDPOINT}/index.php?endpoint=is_user_registered&worker_secret=${WORKER_SECRET}&email=${encodeURIComponent(senderEmail)}`;
      const verificationResponse = await fetch(verificationUrl);

      if (verificationResponse.status !== 200) {
        // If status is not 200, stop processing.
        return;
      }

      const verificationData = await verificationResponse.json();
      if (!verificationData.success || !verificationData.is_registered) {
        // If the API call was not successful or user is not registered, stop.
        return;
      }
    } catch (error) {
      // If the verification call fails for any reason, stop processing.
      return;
    }

    // 2. Sender is verified, now parse the email.
    let parsedEmail;
    try {
        parsedEmail = await parseMime(message.raw);
    } catch (e) {
        // If parsing fails, stop.
        return;
    }


    // 3. Forward the parsed email to the backend to be stored.
    try {
      const uploadUrl = `${PUBLIC_API_ENDPOINT}/index.php?endpoint=email_upload&worker_secret=${WORKER_SECRET}`;

      const uploadResponse = await fetch(uploadUrl, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json'
        },
        body: JSON.stringify({
          from: message.from,
          to: message.to,
          subject: message.headers.get('subject'),
          textContent: parsedEmail.text,
          htmlContent: parsedEmail.html
        })
      });

      // We can check `uploadResponse.ok` if we need to handle upload failures.
      // For now, we'll just complete the process.

    } catch (error) {
      // If the upload fetch call fails, do nothing.
    }
  }
};
