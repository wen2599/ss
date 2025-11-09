// File: Cloudflare Worker (e.g., btt) - Final Merged Version

// ========== 1. Helper Function for UTF-8 to Base64 (for Telegram Proxy) ==========
function utf8_to_b64(str) {
    try {
      const encoder = new TextEncoder();
      const uint8Array = encoder.encode(str);
      let binaryString = '';
      uint8Array.forEach((byte) => {
        binaryString += String.fromCharCode(byte);
      });
      return btoa(binaryString);
    } catch (e) {
      return btoa(unescape(encodeURIComponent(str)));
    }
  }
  
  // ========== 2. Helper Function for Email Stream to String ==========
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
  
  
  export default {
    // ========== 3. FETCH HANDLER (for Telegram Proxy) ==========
    /**
     * Handles HTTP requests. Currently configured to proxy requests for the Telegram Bot.
     * @param {Request} request
     * @param {object} env - Environment variables
     */
    async fetch(request, env) {
      // This part is for the Telegram Bot proxy.
      // It disguises POST requests as multipart/form-data.
      try {
        const TELEGRAM_TARGET_URL = env.TELEGRAM_TARGET_URL; // e.g., 'https://wenge.cloudns.ch/telegram/receiver.php'
        const TELEGRAM_SECRET = env.TELEGRAM_SECRET; // Your Telegram webhook secret
  
        if (!TELEGRAM_TARGET_URL || !TELEGRAM_SECRET) {
          return new Response('Worker environment variables for Telegram are not configured.', { status: 500 });
        }
  
        if (request.method !== 'POST') {
          // We only expect POST from Telegram. For other methods, just say hello.
          return new Response('Worker is active. Ready for Telegram POST or Email events.', { status: 200 });
        }
  
        const telegramUpdate = await request.json();
        const jsonString = JSON.stringify(telegramUpdate);
        const base64Data = utf8_to_b64(jsonString);
  
        const formData = new FormData();
        formData.append('secret', TELEGRAM_SECRET);
        formData.append('data', base64Data);
  
        const response = await fetch(TELEGRAM_TARGET_URL, {
          method: 'POST',
          body: formData,
        });
  
        return new Response(await response.text(), {
          status: response.status,
          headers: response.headers
        });
  
      } catch (error) {
        console.error("Error in Telegram proxy fetch handler:", error.stack);
        return new Response(`Error in worker's fetch handler: ${error.message}`, { status: 500 });
      }
    },
  
    // ========== 4. EMAIL HANDLER (for Mail Processing) ==========
    /**
     * Handles incoming email messages from Cloudflare Email Routing.
     * @param {EmailMessage} message
     * @param {object} env - Environment variables
     */
    async email(message, env) {
      // This part is for receiving emails.
      try {
        const EMAIL_TARGET_URL = env.EMAIL_TARGET_URL; // e.g., 'https://wenge.cloudns.ch/mail/receive.php'
        const EMAIL_SECRET = env.EMAIL_WORKER_SECRET; // Your secret for email communication
  
        if (!EMAIL_TARGET_URL || !EMAIL_SECRET) {
          console.error("Worker environment variables for Email are not configured.");
          // Reject the email to notify sender of a server-side issue.
          message.setReject("Internal server configuration error.");
          return;
        }
  
        // Get the raw email content. It's the most reliable source.
        const rawEmail = await streamToString(message.raw);
        const sender = message.from;
  
        // We send the raw content directly to the backend.
        // The backend is better suited for complex parsing and validation.
        const dataToSend = {
          sender: sender,
          raw_content: rawEmail,
        };
  
        const response = await fetch(EMAIL_TARGET_URL, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'Authorization': `Bearer ${EMAIL_SECRET}` // Use Authorization header for security
          },
          body: JSON.stringify(dataToSend)
        });
  
        if (!response.ok) {
          const errorText = await response.text();
          console.error(`Backend failed to process email. Status: ${response.status}, Body: ${errorText}`);
          // Reject the email if the backend signals an error.
          message.setReject(`Backend server rejected the email with status ${response.status}.`);
        } else {
          console.log(`Email from ${sender} successfully forwarded to backend.`);
        }
        
      } catch (error) {
        console.error("An uncaught error occurred in the email handler:", error.stack);
        // Re-throw the error to let Mail Channels know the processing failed,
        // which may trigger a retry.
        throw error;
      }
    }
  };