// worker/worker.js (Complete version combining proxy and new email handler)

// ========== 1. Helper Functions for Email Parsing ==========

// 将 ReadableStream 转为字符串
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

// quoted-printable 解码
function decodeQuotedPrintable(input) {
  return input
    .replace(/=(?:\r\n|\n|\r)/g, '') // 软换行
    .replace(/=([A-Fa-f0-9]{2})/g, (m, hex) => String.fromCharCode(parseInt(hex, 16)));
}

// Base64 解码 (UTF-8)
function decodeBase64(input) {
  try {
    // Standard base64 decoding
    const binaryString = atob(input.replace(/(\r\n|\n|\r)/gm, ""));
    const bytes = new Uint8Array(binaryString.length);
    for (let i = 0; i < binaryString.length; i++) {
        bytes[i] = binaryString.charCodeAt(i);
    }
    // Decode as UTF-8
    return new TextDecoder('utf-8').decode(bytes);
  } catch (e) {
    console.error("Base64 decoding failed:", e);
    return input; // Return original input if decoding fails
  }
}

// 解析邮件头
function parseHeaders(headers) {
  const headerMap = {};
  const headerLines = headers.split('\r\n');
  let currentHeader = '';
  headerLines.forEach(line => {
    if (line.startsWith(' ') || line.startsWith('\t')) {
      // Continuation of the previous header
      headerMap[currentHeader] += ' ' + line.trim();
    } else if (line.includes(':')) {
      const parts = line.split(':');
      currentHeader = parts[0].toLowerCase();
      headerMap[currentHeader] = parts.slice(1).join(':').trim();
    }
  });
  return headerMap;
}


// 解析邮件主题
function decodeSubject(subject) {
    if (!subject) return 'No Subject';
    // Matches RFC 2047 encoded-word format: =?charset?encoding?encoded-text?=
    return subject.replace(/=\?([^?]+)\?([BQ])\?([^?]+)\?=/gi, (match, charset, encoding, encodedText) => {
        try {
            const decoder = new TextDecoder(charset);
            if (encoding.toUpperCase() === 'B') {
                const binaryString = atob(encodedText);
                const bytes = new Uint8Array(binaryString.length);
                for (let i = 0; i < binaryString.length; i++) {
                    bytes[i] = binaryString.charCodeAt(i);
                }
                return decoder.decode(bytes);
            } else if (encoding.toUpperCase() === 'Q') {
                return decoder.decode(
                    new Uint8Array(
                        decodeQuotedPrintable(encodedText.replace(/_/g, ' ')).split('').map(c => c.charCodeAt(0))
                    )
                );
            }
        } catch (e) {
            console.error(`Failed to decode subject part: ${match}`, e);
        }
        return encodedText; // Return as-is if decoding fails
    });
}


// ========== 2. Core Worker Logic ==========

export default {
  /**
   * Handles HTTP requests, acting as a proxy for the backend API.
   */
  async fetch(request, env, ctx) {
    const url = new URL(request.url);
    // Use the environment variable for the backend server, with a fallback.
    const backendServer = env.PUBLIC_API_ENDPOINT ? new URL(env.PUBLIC_API_ENDPOINT).origin : "https://wenge.cloudns.ch";

    // Proxy API calls (e.g., /login, /register, etc.) to the backend
    if (url.pathname.endsWith('.php') || url.pathname.startsWith('/api/')) {
      const backendUrl = new URL(url.pathname, backendServer);
      backendUrl.search = url.search;

      // Forward the request as-is to the backend.
      const backendRequest = new Request(backendUrl, request);
      return fetch(backendRequest);
    }

    // For any other request, return a simple "Not Found" or handle as needed.
    return new Response('Not found.', { status: 404 });
  },

  /**
   * Handles incoming email messages.
   */
  async email(message, env, ctx) {
    const { PUBLIC_API_ENDPOINT, EMAIL_HANDLER_SECRET } = env;

    // Critical check for environment variables
    if (!PUBLIC_API_ENDPOINT || !EMAIL_HANDLER_SECRET) {
      console.error("Worker Email Handler: Missing required environment variables (PUBLIC_API_ENDPOINT or EMAIL_HANDLER_SECRET).");
      // We don't want to retry this, as it's a configuration issue.
      return;
    }

    const senderEmail = message.from;
    console.log(`Worker Email Handler: Received email from: ${senderEmail}`);
    console.log(`Worker Email Handler Debug: EMAIL_HANDLER_SECRET from env is: [${EMAIL_HANDLER_SECRET ? 'SET' : 'NOT SET'}]`);

    try {
      // Step 1: Verify if the user is registered in the backend.
      // --- FIX: CONSTRUCT THE URL CORRECTLY WITH 'action' AS A QUERY PARAMETER ---
      const verificationUrl = `${PUBLIC_API_ENDPOINT}?action=is_user_registered&worker_secret=${EMAIL_HANDLER_SECRET}&email=${encodeURIComponent(senderEmail)}`;
      console.log(`Worker Email Handler: Calling verification URL: ${verificationUrl}`);
      
      const verificationResponse = await fetch(verificationUrl);

      if (!verificationResponse.ok) {
        console.error(`Worker Email Handler: User verification request failed with status: ${verificationResponse.status}.`);
        // Stop processing, but don't cause a retry for auth failures (4xx)
        if (verificationResponse.status >= 400 && verificationResponse.status < 500) {
           return;
        }
        // For server errors (5xx), we might want to let the email be retried.
        throw new Error(`Verification failed with status ${verificationResponse.status}`);
      }
      
      const verificationData = await verificationResponse.json();
      console.log("Worker Email Handler: Verification response received:", JSON.stringify(verificationData));

      if (!verificationData.success || !verificationData.is_registered) {
        console.log(`Worker Email Handler: User ${senderEmail} is not registered or verification failed. Discarding email.`);
        return; // Stop processing, user is not authorized.
      }

      console.log(`Worker Email Handler: User ${senderEmail} is verified. Proceeding to forward email.`);

      // Step 2: If user is verified, parse and forward the email content.
      const rawEmail = await streamToString(message.raw);
      const headers = parseHeaders(rawEmail.split(/\r\n\r\n/)[0]);
      const subject = decodeSubject(headers['subject']);
      const body = rawEmail; // Forward the full raw email content as the body for now

      const formData = new FormData();
      formData.append('worker_secret', EMAIL_HANDLER_SECRET);
      formData.append('from', senderEmail);
      formData.append('to', message.to);
      formData.append('subject', subject);
      formData.append('body', body);
      
      // --- FIX: Ensure the POST request URL is correct without extra path segments ---
      const postUrl = `${PUBLIC_API_ENDPOINT}?action=process_email`;
      console.log(`Worker Email Handler: Posting email content to: ${postUrl}`);

      const postResponse = await fetch(postUrl, {
        method: 'POST',
        body: formData,
      });

      if (!postResponse.ok) {
        const errorText = await postResponse.text();
        console.error(`Worker Email Handler: Failed to forward email content. Status: ${postResponse.status}, Body: ${errorText}`);
        throw new Error(`Failed to post email with status ${postResponse.status}`);
      }

      const postData = await postResponse.json();
      console.log('Worker Email Handler: Email forwarded successfully. Backend response:', JSON.stringify(postData));

    } catch (error) {
      console.error('Worker Email Handler: An unexpected error occurred:', error.message);
      // Re-throw the error to signal a retry to the Mail Channels service.
      throw error;
    }
  }
};
