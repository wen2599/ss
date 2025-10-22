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


// ========== 2. New Helper Function for Body Extraction ==========

/**
 * Parses a raw email string to find and decode the HTML body.
 * @param {string} rawEmail - The full raw email content.
 * @returns {string} The decoded HTML body, or a fallback.
 */
function extractHTMLBody(rawEmail) {
  const headers = parseHeaders(rawEmail.split(/\r\n\r\n/)[0]);
  const contentType = headers['content-type'] || '';
  const bodyStartIndex = rawEmail.indexOf('\r\n\r\n');

  if (bodyStartIndex === -1) {
    return ''; // No body found
  }
  const emailBody = rawEmail.substring(bodyStartIndex + 4);

  if (contentType.includes('multipart/')) {
    const boundaryMatch = contentType.match(/boundary="?([^"]+)"?/);
    if (!boundaryMatch) {
      return emailBody; // Cannot parse without boundary, return raw body
    }

    // The boundary in the body is prefixed with "--"
    const boundary = `--${boundaryMatch[1]}`;
    const parts = emailBody.split(new RegExp(`\\s*${boundary}(--)?\\s*`));

    let htmlPart = '';
    let textPart = '';

    for (const part of parts) {
      // Add a check to ensure 'part' is a string before calling .trim()
      if (typeof part !== 'string' || !part.trim()) continue;

      const partHeadersEndIndex = part.indexOf('\r\n\r\n');
      if (partHeadersEndIndex === -1) continue;

      const partHeadersRaw = part.substring(0, partHeadersEndIndex);
      const partBody = part.substring(partHeadersEndIndex + 4);
      const partHeaders = parseHeaders(partHeadersRaw);

      const partContentType = partHeaders['content-type'] || '';
      const contentEncoding = (partHeaders['content-transfer-encoding'] || '').toLowerCase();
      const charsetMatch = partContentType.match(/charset="?([^"]+)"?/i);
      const charset = charsetMatch ? charsetMatch[1].toLowerCase() : 'utf-8'; // Default to utf-8

      let decodedPartBody;
      const decoder = new TextDecoder(charset);

      if (contentEncoding === 'base64') {
        // The existing decodeBase64 function assumes UTF-8, which is wrong.
        // We need to decode to binary first, then use the correct charset decoder.
        try {
          const binaryString = atob(partBody.replace(/(\r\n|\n|\r)/gm, ""));
          const bytes = new Uint8Array(binaryString.length);
          for (let i = 0; i < binaryString.length; i++) {
              bytes[i] = binaryString.charCodeAt(i);
          }
          decodedPartBody = decoder.decode(bytes);
        } catch(e) {
          console.error("Base64 decoding with charset failed:", e);
          decodedPartBody = partBody; // Fallback
        }
      } else if (contentEncoding === 'quoted-printable') {
        // Quoted-printable needs to be decoded into raw bytes and then decoded with the correct charset.
        const decodedBytes = new Uint8Array(
          decodeQuotedPrintable(partBody).split('').map(c => c.charCodeAt(0))
        );
        decodedPartBody = decoder.decode(decodedBytes);
      } else {
        decodedPartBody = partBody; // Assume plain text if no encoding
      }

      if (partContentType.includes('text/html')) {
        htmlPart = decodedPartBody;
        // Don't break; a later part might be a better match (e.g. multipart/alternative)
      } else if (partContentType.includes('text/plain')) {
        textPart = decodedPartBody;
      }
    }
    // Prefer HTML, fallback to text, then to the raw body
    return htmlPart || textPart || emailBody;
  } else {
    // Not a multipart email, so the whole body is the content.
    const contentEncoding = (headers['content-transfer-encoding'] || '').toLowerCase();
    const charsetMatch = contentType.match(/charset="?([^"]+)"?/i);
    const charset = charsetMatch ? charsetMatch[1].toLowerCase() : 'utf-8';
    const decoder = new TextDecoder(charset);

    if (contentEncoding === 'base64') {
      try {
        const binaryString = atob(emailBody.replace(/(\r\n|\n|\r)/gm, ""));
        const bytes = new Uint8Array(binaryString.length);
        for (let i = 0; i < binaryString.length; i++) {
            bytes[i] = binaryString.charCodeAt(i);
        }
        return decoder.decode(bytes);
      } catch(e) {
        return emailBody;
      }
    } else if (contentEncoding === 'quoted-printable') {
      const decodedBytes = new Uint8Array(
        decodeQuotedPrintable(emailBody).split('').map(c => c.charCodeAt(0))
      );
      return decoder.decode(decodedBytes);
    }
    return emailBody;
  }
}


// ========== 3. Core Worker Logic ==========

export default {
  /**
   * Handles HTTP requests, acting as a proxy for the backend API.
   */
  async fetch(request, env, ctx) {
    const url = new URL(request.url);
    // Use the environment variable for the backend server, with a fallback.
    const backendServer = env.PUBLIC_API_ENDPOINT ? new URL(env.PUBLIC_API_ENDPOINT).origin : "https://wenge.cloudns.ch";

    // Route for the new AI processing action
    if (url.pathname === '/process-ai') {
      if (request.method !== 'POST') {
        return new Response('Method Not Allowed', { status: 405 });
      }

      try {
        const { email_content } = await request.json();
        if (!email_content) {
          return new Response('Missing email_content in request body', { status: 400 });
        }

        const prompt = `You are an expert financial assistant. Your task is to extract structured data from the following email content. The email is a bill or invoice. Please extract the following fields: vendor_name, bill_amount (as a number), currency (e.g., USD, CNY), due_date (in YYYY-MM-DD format), invoice_number, and a category (e.g., "Utilities", "Subscription", "Shopping", "Travel"). If a field is not present, its value should be null. Provide the output in a clean JSON format. Do not include any explanatory text, only the JSON object.\n\nEmail Content:\n"""\n${email_content}\n"""`;

        const response = await env.AI.run('@cf/meta/llama-2-7b-chat-int8', {
          prompt
        });

        // The AI model's response is often wrapped in ```json ... ```, so we need to extract it.
        const jsonMatch = response.response.match(/```json\n([\s\S]*?)\n```/);
        const jsonResponse = jsonMatch ? jsonMatch[1] : response.response;

        // Return the extracted JSON directly to the caller (our PHP backend)
        return new Response(jsonResponse, {
          headers: { 'Content-Type': 'application/json' },
        });

      } catch (error) {
        console.error('AI processing error:', error);
        return new Response(`Error processing AI request: ${error.message}`, { status: 500 });
      }
    }

    // Proxy API calls (e.g., /login, /register, etc.) to the backend
    if (url.pathname.startsWith('/api/')) {
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
      const verificationUrl = `${PUBLIC_API_ENDPOINT}/api/users/is-registered?worker_secret=${EMAIL_HANDLER_SECRET}&email=${encodeURIComponent(senderEmail)}`;
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

      if (verificationData.status !== 'success' || !verificationData.data.is_registered) {
        console.log(`Worker Email Handler: User ${senderEmail} is not registered or verification failed. Discarding email.`);
        return; // Stop processing, user is not authorized.
      }

      const userId = verificationData.data.user_id;
      if (!userId) {
        console.error(`Worker Email Handler: Verification successful but no user_id returned for ${senderEmail}. Discarding.`);
        return;
      }

      console.log(`Worker Email Handler: User ${senderEmail} is verified with user_id: ${userId}. Proceeding to forward email.`);

      // Step 2: If user is verified, parse and forward the email content.
      const rawEmail = await streamToString(message.raw);
      const headers = parseHeaders(rawEmail.split(/\r\n\r\n/)[0]);
      const subject = decodeSubject(headers['subject']);
      const body = extractHTMLBody(rawEmail); // Use the new function to get clean HTML

      const postData = {
          worker_secret: EMAIL_HANDLER_SECRET,
          from: senderEmail,
          to: message.to,
          subject: subject,
          body: body,
          user_id: userId,
      };
      
      // --- FIX: Ensure the POST request URL is correct without extra path segments ---
      const postUrl = `${PUBLIC_API_ENDPOINT}/api/emails`;
      console.log(`Worker Email Handler: Posting email content to: ${postUrl}`);

      const postResponse = await fetch(postUrl, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(postData),
      });

      if (!postResponse.ok) {
        const errorText = await postResponse.text();
        console.error(`Worker Email Handler: Failed to forward email content. Status: ${postResponse.status}, Body: ${errorText}`);
        throw new Error(`Failed to post email with status ${postResponse.status}`);
      }

      const responseData = await postResponse.json();
      console.log('Worker Email Handler: Email forwarded successfully. Backend response:', JSON.stringify(responseData));

    } catch (error) {
      console.error('Worker Email Handler: An unexpected error occurred:', error.message);
      // Re-throw the error to signal a retry to the Mail Channels service.
      throw error;
    }
  }
};
