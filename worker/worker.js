/**
 * Helper function to read a ReadableStream into a string.
 * @param {ReadableStream} stream The stream to read.
 * @returns {Promise<string>} A promise that resolves with the string content.
 */
async function streamToString(stream) {
  const reader = stream.getReader();
  const decoder = new TextDecoder();
  let result = '';
  while (true) {
    const { done, value } = await reader.read();
    if (done) break;
    result += decoder.decode(value, { stream: true });
  }
  return result;
}

/**
 * A simplified parser to extract the plain text body from a raw email string.
 * NOTE: This is not a comprehensive MIME parser and may not work for all email formats.
 * It primarily looks for the first `text/plain` content type.
 * @param {string} rawEmail The raw email content.
 * @returns {string} The extracted plain text body.
 */
function getPlainTextBody(rawEmail) {
  try {
    // First, try to find a `text/plain` part in a multipart message
    const contentTypeMatch = rawEmail.match(/^Content-Type: multipart\/alternative; boundary="(.+)"$/im);
    if (contentTypeMatch) {
      const boundary = contentTypeMatch[1];
      const parts = rawEmail.split(`--${boundary}`);
      for (const part of parts) {
        if (part.includes('Content-Type: text/plain')) {
          // Get content after the headers of the part
          const bodyMatch = part.split(/\r?\n\r?\n/);
          if (bodyMatch.length > 1) {
            return bodyMatch.slice(1).join('\r\n\r\n').trim();
          }
        }
      }
    }

    // If not multipart, or if the logic above fails, try a simpler heuristic
    // This is for emails that are sent as plain text directly
    if (rawEmail.toLowerCase().includes('content-type: text/plain')) {
        const bodyMatch = rawEmail.split(/\r?\n\r?\n/);
        if (bodyMatch.length > 1) {
             return bodyMatch.slice(1).join('\r\n\r\n').trim();
        }
    }
    
    // As a last resort, if no content-type is specified, return the body after the first double newline.
    const fallbackBody = rawEmail.split(/\r?\n\r?\n/);
    if(fallbackBody.length > 1) {
        return fallbackBody.slice(1).join('\r\n\r\n').trim();
    }

    return ''; // Return empty string if no body is found
  } catch (e) {
    console.error("Body parsing failed: ", e);
    return '';
  }
}


export default {
  /**
   * Handles HTTP requests for API proxying and static asset serving.
   */
  async fetch(request, env) {
    const url = new URL(request.url);
    // The backend URL should be configured in your Cloudflare dashboard under
    // Settings -> Variables -> Environment Variables for production deployments.
    const backendUrl = env.BACKEND_URL || 'https://wenge.cloudns.ch';

    const apiPaths = [
      '/get_numbers',
      '/check_session',
      '/login',
      '/logout',
      '/register',
      '/is_user_registered',
      '/email_upload',
      '/tg_webhook',
      '/get_bills',
      '/validate_user_email',
      '/email_receiver',
    ];

    if (apiPaths.some(path => url.pathname.includes(path))) {
      const newUrl = new URL(url.pathname, backendUrl);
      newUrl.search = url.search;

      const newRequest = new Request(newUrl, {
        method: request.method,
        headers: request.headers,
        body: request.body,
        redirect: 'follow'
      });

      return fetch(newRequest);
    }

    return env.ASSETS.fetch(request);
  },

  /**
   * Handles incoming emails via Cloudflare Email Routing.
   */
  async email(message, env) {
    const backendUrl = env.BACKEND_URL || 'https://wenge.cloudns.ch';
    const workerSecret = env.WORKER_SECRET;
    const sender = message.from;

    if (!workerSecret) {
        console.error('WORKER_SECRET is not defined. Cannot proceed with validation.');
        message.setReject('Internal server configuration error.');
        return;
    }

    // 1. Validate the sender's email address
    const validationUrl = `${backendUrl}/backend/endpoints/validate_user_email.php?email=${encodeURIComponent(sender)}`;
    const validationRequest = new Request(validationUrl, {
      method: 'GET',
      headers: { 'Authorization': `Bearer ${workerSecret}` }
    });

    try {
      const validationResponse = await fetch(validationRequest);
      const validationResult = await validationResponse.json();

      if (!validationResult.is_valid) {
        console.log(`REJECTED: Email from unregistered user "${sender}". Reason: ${validationResult.error || 'Not registered'}`);
        message.setReject(`Your email address, ${sender}, is not authorized for this service.`);
        return;
      }

      console.log(`ACCEPTED: Email from registered user "${sender}".`);

      // 2. If valid, parse and forward the email to the backend receiver
      const rawEmail = await streamToString(message.raw);
      const bodyText = getPlainTextBody(rawEmail);

      const emailData = {
        from: sender,
        subject: message.headers.get('subject') || '',
        body_text: bodyText,
        body_html: '', // HTML parsing is not supported in this version
        message_id: message.headers.get('message-id') || ''
      };
      
      const receiverUrl = `${backendUrl}/backend/endpoints/email_receiver.php`;
      const receiverRequest = new Request(receiverUrl, {
          method: 'POST',
          headers: {
              'Content-Type': 'application/json',
              'Authorization': `Bearer ${workerSecret}`
          },
          body: JSON.stringify(emailData)
      });

      const receiverResponse = await fetch(receiverRequest);
      if (receiverResponse.ok) {
          console.log(`SUCCESS: Email from ${sender} processed by backend.`);
      } else {
          const errorText = await receiverResponse.text();
          console.error(`ERROR: Backend failed for email from ${sender}. Status: ${receiverResponse.status}. Body: ${errorText}`);
          message.setReject('The server failed to process your email.');
      }

    } catch (error) {
      console.error(`FATAL: Exception during email processing for ${sender}. Error: ${error.message}`);
      message.setReject('An unexpected error occurred.');
    }
  }
};