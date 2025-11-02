/**
 * Cloudflare Worker for processing incoming emails via Email Routing.
 * This worker forwards email content to a backend server for further processing.
 */

export default {
  /**
   * The fetch handler is called for every event.
   * For emails, the `request` parameter is actually an `EmailMessage` object.
   * @param {EmailMessage} message - The email message object.
   * @param {object} env - Environment variables bound to the worker.
   * @param {object} ctx - The execution context.
   */
  async email(message, env, ctx) {
    // --- Configuration ---
    // These should be set as environment variables in the Cloudflare Worker settings
    // for better security and management.
    const BACKEND_ENDPOINT = env.BACKEND_ENDPOINT || 'https://wenge.cloudns.ch/proxy.php?action=receive_email';
    const WORKER_SECRET = env.WORKER_SECRET || 'your_secret_key_shared_with_cf_worker';
    
    // --- Security Check: SPF and DKIM ---
    // Emails failing checks will be marked as spam instead of being rejected.
    let isSpam = false;
    if (message.dkim !== 'pass' && message.spf !== 'pass') {
      console.log(`Email from ${message.from} failed both DKIM and SPF checks. Marking as spam.`);
      isSpam = true;
    } else {
      console.log(`Received a valid email from: ${message.from} to: ${message.to}`);
    }

    // --- Prepare Data for Backend ---
    // We need the sender's email and the full raw content of the email.
    const sender_email = message.from;
    
    // Create a stream from the raw email content.
    const rawEmailStream = message.raw;
    
    // Read the stream into a string.
    const reader = rawEmailStream.getReader();
    let chunks = [];
    let done, value;
    while (!done) {
      ({ value, done } = await reader.read());
      if (done) break;
      chunks.push(value);
    }
    // The raw email content is a Uint8Array, we need to decode it to a string.
    let raw_email_content = new TextDecoder("utf-8").decode(
      new Uint8Array(chunks.reduce((acc, chunk) => [...acc, ...chunk], []))
    );

    // If the email was flagged as spam, prepend a warning to the subject line.
    if (isSpam) {
      raw_email_content = raw_email_content.replace(
        /^Subject: /im,
        "Subject: [SPAM] "
      );
    }

    const postData = {
      sender_email: sender_email,
      raw_email: raw_email_content,
    };

    // --- Send Data to Backend ---
    try {
      const response = await fetch(BACKEND_ENDPOINT, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          // This custom header is used by our backend's proxy.php to verify the request's origin.
          'X-Worker-Secret': WORKER_SECRET,
        },
        body: JSON.stringify(postData),
      });

      // Log the response from the backend for debugging purposes.
      const responseBody = await response.text();
      console.log(`Backend responded with status: ${response.status}`);
      console.log(`Backend response body: ${responseBody}`);

      if (!response.ok) {
        // If the backend returns an error (e.g., 4xx or 5xx),
        // we should reject the email so the sender is notified.
        console.error('Backend failed to process the email. Rejecting.');
        message.setReject(`Failed to process your submission. Please try again later. (Status: ${response.status})`);
      } else {
        console.log('Email successfully forwarded to and accepted by the backend.');
        // If the backend successfully accepts it, we don't need to do anything else.
        // The email processing is considered complete.
      }

    } catch (error) {
      console.error('Error forwarding email to backend:', error);
      // If there's a network error connecting to our backend, reject the email.
      message.setReject('The processing server is temporarily unavailable. Please try again later.');
    }
  },
};