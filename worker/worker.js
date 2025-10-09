import PostalMime from 'postal-mime';

// --- Utility Functions for Email Parsing ---
async function parseMime(mimeStream) {
    const reader = mimeStream.getReader();
    const chunks = [];
    let done, value;
    while (!({ done, value } = await reader.read()), done) {
        chunks.push(value);
    }
    const totalLength = chunks.reduce((acc, chunk) => acc + chunk.length, 0);
    const combinedChunks = new Uint8Array(totalLength);
    let offset = 0;
    for (const chunk of chunks) {
        combinedChunks.set(chunk, offset);
        offset += chunk.length;
    }
    const rawEmail = new TextDecoder("utf-8").decode(combinedChunks);
    const parser = new PostalMime();
    return await parser.parse(rawEmail);
}

export default {
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
      let headers = new Headers(request.headers);
      if (requestedEndpoint === 'telegram_webhook') {
         if (env.TELEGRAM_WEBHOOK_SECRET) {
            headers.set('X-Telegram-Bot-Api-Secret-Token', env.TELEGRAM_WEBHOOK_SECRET);
         }
      }
      const backendRequest = new Request(backendUrl, {
        method: request.method,
        headers: headers,
        body: request.body,
        redirect: 'follow'
      });
      return fetch(backendRequest);
    }
    return env.ASSETS.fetch(request);
  },

  async email(message, env, ctx) {
    const { WORKER_SECRET, PUBLIC_API_ENDPOINT } = env;
    if (!WORKER_SECRET || !PUBLIC_API_ENDPOINT) {
      return;
    }
    const senderEmail = message.from;
    try {
      const verificationUrl = `${PUBLIC_API_ENDPOINT}/index.php?endpoint=is_user_registered&worker_secret=${WORKER_SECRET}&email=${encodeURIComponent(senderEmail)}`;
      const verificationResponse = await fetch(verificationUrl);
      if (verificationResponse.status !== 200) return;
      const verificationData = await verificationResponse.json();
      if (!verificationData.success || !verificationData.is_registered) return;
    } catch (error) {
      return;
    }
    let parsedEmail;
    try {
        parsedEmail = await parseMime(message.raw);
    } catch (e) {
        return;
    }
    try {
      const uploadUrl = `${PUBLIC_API_ENDPOINT}/index.php?endpoint=email_upload&worker_secret=${WORKER_SECRET}`;
      const payload = {
        from: message.from,
        to: message.to,
        subject: message.headers.get('subject'),
        // 同时提供 text_content/html_content 和 textContent/htmlContent
        text_content: parsedEmail.text,
        html_content: parsedEmail.html,
        textContent: parsedEmail.text,
        htmlContent: parsedEmail.html
      };
      await fetch(uploadUrl, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json'
        },
        body: JSON.stringify(payload)
      });
    } catch (error) {}
  }
};
