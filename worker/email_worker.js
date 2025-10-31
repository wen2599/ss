/**
 * File: email_worker.js
 * Description: Cloudflare Worker to receive emails, parse them, and securely forward them to a PHP backend.
 * This worker is designed to be robust, with retry logic and structured logging.
 * Version: 2.1 - Refactored to use npm import for postal-mime.
 */

// Use modern ES6 import for the postal-mime library.
// This is the standard and recommended way for Cloudflare Workers.
import PostalMime from 'postal-mime';

/**
 * A helper function for structured JSON logging.
 * @param {string} level - Log level ('INFO', 'WARN', 'ERROR').
 * @param {string} message - The primary log message.
 * @param {object} data - Additional contextual data.
 */
function log(level, message, data = {}) {
  console.log(JSON.stringify({
    timestamp: new Date().toISOString(),
    level,
    message,
    ...data,
  }));
}

/**
 * Forwards the parsed email payload to the backend API with a retry mechanism.
 * @param {object} payload - The JSON payload to send to the backend.
 * @param {string} url - The target backend URL.
 * @param {string} secret - The secret key to authenticate with the backend.
 * @param {string} messageId - The email's message-id for logging.
 * @returns {Promise<Response>} The final response from the backend.
 */
async function forwardToBackend(payload, url, secret, messageId) {
  const MAX_RETRIES = 3;
  const INITIAL_DELAY_MS = 1000; // Using exponential backoff for retries

  for (let attempt = 1; attempt <= MAX_RETRIES; attempt++) {
    log('INFO', `Forwarding email to backend`, { messageId, attempt, maxAttempts: MAX_RETRIES });

    try {
      const response = await fetch(url, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-WORKER-SECRET': secret, // Correct header name as expected by the PHP backend
        },
        body: JSON.stringify(payload),
      });

      if (response.ok) {
        log('INFO', 'Backend acknowledged the email successfully.', { messageId, status: response.status });
        return response; // Success, exit the loop
      }

      // If the response is not ok, log the details and prepare for retry
      const errorBody = await response.text();
      log('WARN', 'Backend returned a non-ok response.', { 
        messageId, 
        attempt, 
        status: response.status, 
        responseBody: errorBody 
      });

    } catch (error) {
      log('ERROR', 'Network or fetch error while forwarding to backend.', { 
        messageId, 
        attempt, 
        errorMessage: error.message,
      });
    }

    // If it's not the last attempt, wait before retrying
    if (attempt < MAX_RETRIES) {
      const delay = INITIAL_DELAY_MS * Math.pow(2, attempt - 1);
      await new Promise(resolve => setTimeout(resolve, delay));
    }
  }

  // If all retries fail, throw a final error to be caught by the main handler
  throw new Error(`Failed to forward email after ${MAX_RETRIES} attempts.`);
}

export default {
  /**
   * The main entry point for the Cloudflare Email Worker.
   * @param {EmailMessage} message - The incoming email message object.
   * @param {object} env - Environment variables set in the Cloudflare dashboard.
   * @param {object} ctx - The execution context.
   */
  async email(message, env, ctx) {
    const { BACKEND_URL, WORKER_SECRET } = env;
    const messageId = message.headers.get('message-id') || `no-id-${Date.now()}`;

    // --- Configuration Check ---
    if (!BACKEND_URL || !WORKER_SECRET) {
      log('ERROR', 'Worker environment variables (BACKEND_URL or WORKER_SECRET) are not set!', { messageId });
      message.setReject('Worker configuration error. Please contact the administrator.');
      return;
    }
    
    const fullApiUrl = `${BACKEND_URL.replace(/\/$/, '')}/receive_email.php`;

    try {
      // 1. Parse the incoming raw email stream into a structured object.
      // The 'new PostalMime()' call will now work correctly.
      const parser = new PostalMime();
      const parsedEmail = await parser.parse(message.raw);

      // 2. Construct the payload for our backend.
      const payload = {
        message_id: messageId,
        from: parsedEmail.from?.address || message.from,
        to: parsedEmail.to?.map(t => t.address).join(', ') || message.to,
        subject: parsedEmail.subject || '',
        text: parsedEmail.text || '',
        html: parsedEmail.html || null,
      };

      // 3. Forward the payload to the backend with retry logic.
      await forwardToBackend(payload, fullApiUrl, WORKER_SECRET, messageId);

      log('INFO', 'Email processing complete and forwarded successfully.', { messageId, from: payload.from });

    } catch (error) {
      log('ERROR', 'Critical error during worker execution after all retries.', { 
        messageId, 
        errorMessage: error.message,
      });
      message.setReject(`Failed to process and forward the email due to a persistent backend issue.`);
    }
  },
};