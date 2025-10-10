/**
 * Welcome to Cloudflare Workers!
 *
 * This worker handles two main tasks:
 * 1. API Gateway: Proxies and rewrites frontend API requests to the backend server.
 * 2. Email Handler: Receives emails via Cloudflare Email Routing, verifies the sender,
 *    parses the email, and forwards the content to a backend API endpoint.
 *
 * This version includes significant improvements in robustness, error handling, and maintainability.
 */

import PostalMime from 'postal-mime';

// --- Worker Entry Point ---
export default {
  /**
   * Handles all incoming HTTP requests.
   * - If the path starts with /api/, it acts as an API gateway.
   * - Otherwise, it serves static assets for the frontend application.
   */
  async fetch(request, env, ctx) {
    const url = new URL(request.url);
    const backendServer = env.PUBLIC_API_ENDPOINT || "https://wenge.cloudns.ch";

    // --- API Gateway Logic ---
    if (url.pathname.startsWith('/api/')) {
      // No more hardcoded endpoint list. Any /api/ request is proxied.
      const endpoint = url.pathname.substring(5);

      // This path is now definitive based on our successful debugging.
      const backendUrl = new URL(`${backendServer}/public/index.php?endpoint=${endpoint}`);

      // Preserve original query parameters
      backendUrl.search = url.search;

      const requestHeaders = new Headers(request.headers);
      requestHeaders.set('Host', new URL(backendServer).hostname);

      // Add Telegram secret header if it's the webhook endpoint.
      if (endpoint === 'telegramWebhook' && env.TELEGRAM_WEBHOOK_SECRET) {
        requestHeaders.set('X-Telegram-Bot-Api-Secret-Token', env.TELEGRAM_WEBHOOK_SECRET);
      }

      try {
        const backendRequest = new Request(backendUrl.toString(), new Request(request, { headers: requestHeaders }));
        const backendResponse = await fetch(backendRequest);

        // Create a new response with mutable headers to add CORS.
        const responseHeaders = new Headers(backendResponse.headers);
        const origin = request.headers.get('Origin');
        if (origin) {
          responseHeaders.set('Access-Control-Allow-Origin', origin);
          responseHeaders.set('Vary', 'Origin');
        }
        responseHeaders.set('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS');
        responseHeaders.set('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Telegram-Bot-Api-Secret-Token');

        return new Response(backendResponse.body, {
          status: backendResponse.status,
          statusText: backendResponse.statusText,
          headers: responseHeaders,
        });

      } catch (error) {
        console.error(`[Worker Fetch Error] Failed to proxy to backend: ${error.message}`);
        const errorResponse = { error: 'Backend Proxy Error', message: error.message };
        return new Response(JSON.stringify(errorResponse), {
          status: 502,
          headers: {
            'Content-Type': 'application/json',
            'Access-Control-Allow-Origin': '*'
          }
        });
      }
    }

    // --- Static Asset Serving ---
    return env.ASSETS.fetch(request);
  },

  /**
   * Handles incoming emails routed by Cloudflare Email Routing.
   */
  async email(message, env, ctx) {
    const { WORKER_SECRET, PUBLIC_API_ENDPOINT } = env;
    if (!WORKER_SECRET || !PUBLIC_API_ENDPOINT) {
      console.error('[Worker Email Error] Missing essential environment variables: WORKER_SECRET or PUBLIC_API_ENDPOINT.');
      return;
    }

    const senderEmail = message.from;
    console.log(`[Worker Email] Received email from: ${senderEmail}`);

    // 1. Verify if the sender is a registered user.
    try {
      const verificationUrl = `${PUBLIC_API_ENDPOINT}/public/index.php?endpoint=is_user_registered&worker_secret=${WORKER_SECRET}&email=${encodeURIComponent(senderEmail)}`;
      const verificationResponse = await fetch(verificationUrl);

      if (!verificationResponse.ok) {
        const errorText = await verificationResponse.text();
        console.error(`[Worker Email Error] User verification API call failed with status ${verificationResponse.status}. Body: ${errorText}`);
        return;
      }

      const verificationData = await verificationResponse.json();
      if (!verificationData.is_registered) {
        console.log(`[Worker Email] Sender ${senderEmail} is not a registered user. Discarding email.`);
        return;
      }
      console.log(`[Worker Email] Sender ${senderEmail} verified successfully.`);
    } catch (error) {
      console.error(`[Worker Email Error] Network error during user verification: ${error.message}`);
      return;
    }

    // 2. Parse the email content.
    let parsedEmail;
    try {
        const parser = new PostalMime();
        parsedEmail = await parser.parse(message.raw);
        console.log(`[Worker Email] Successfully parsed email with subject: "${parsedEmail.subject}"`);
    } catch (e) {
        console.error(`[Worker Email Error] Failed to parse MIME content: ${e.message}`);
        return;
    }

    // 3. Forward the parsed email to the backend.
    try {
      const uploadUrl = `${PUBLIC_API_ENDPOINT}/public/index.php?endpoint=email_upload&worker_secret=${WORKER_SECRET}`;
      const uploadResponse = await fetch(uploadUrl, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          from: message.from,
          to: message.to,
          subject: parsedEmail.subject,
          textContent: parsedEmail.text,
          htmlContent: parsedEmail.html
        })
      });

      if (!uploadResponse.ok) {
        const errorText = await uploadResponse.text();
        console.error(`[Worker Email Error] Email upload API call failed with status ${uploadResponse.status}. Body: ${errorText}`);
        return;
      }

      console.log(`[Worker Email] Successfully forwarded email from ${senderEmail} to backend.`);

    } catch (error) {
      console.error(`[Worker Email Error] Network error during email upload: ${error.message}`);
    }
  }
};