export default {
  /**
   * This is a special diagnostic worker.
   * When it receives any request, it will try to send a message to your Telegram
   * containing all the headers it received. This will definitively show us
   * if the secret token is arriving at the worker.
   */
  async fetch(request, env, ctx) {
    // Get Telegram credentials from the worker's environment variables.
    // IMPORTANT: These must be set in your Cloudflare project settings.
    const botToken = env.TELEGRAM_BOT_TOKEN;
    const adminChatId = env.TELEGRAM_ADMIN_CHAT_ID;

    // Check if the required environment variables are set.
    if (!botToken || !adminChatId) {
      const errorMessage = 'CRITICAL WORKER ERROR: TELEGRAM_BOT_TOKEN and/or TELEGRAM_ADMIN_CHAT_ID are not set in the Cloudflare project environment variables.';
      console.error(errorMessage);
      // Return an error response so we know the worker itself has a configuration problem.
      return new Response(errorMessage, { status: 500 });
    }

    // --- Create the diagnostic message ---
    let diagnosticMessage = "<b>Diagnostic Report from Cloudflare Worker</b>\n\n";
    diagnosticMessage += "A request was received by the worker. Here are the headers:\n\n";

    // Collect all headers from the incoming request.
    const headers = {};
    for (const [key, value] of request.headers.entries()) {
      headers[key] = value;
    }

    // Format the headers into a readable string.
    diagnosticMessage += `<pre>${JSON.stringify(headers, null, 2)}</pre>`;

    // Look for the secret token specifically.
    const secretTokenHeader = request.headers.get('x-telegram-bot-api-secret-token');
    if (secretTokenHeader) {
      diagnosticMessage += "\n\n<b>✅ SUCCESS:</b> The 'x-telegram-bot-api-secret-token' header was found.";
    } else {
      diagnosticMessage += "\n\n<b>❌ FAILURE:</b> The 'x-telegram-bot-api-secret-token' header was NOT found.";
    }

    // --- Send the diagnostic message to Telegram ---
    const telegramApiUrl = `https://api.telegram.org/bot${botToken}/sendMessage`;

    try {
      await fetch(telegramApiUrl, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          chat_id: adminChatId,
          text: diagnosticMessage,
          parse_mode: 'HTML',
        }),
      });
    } catch (e) {
      // If sending the message fails, we can't do much, but we'll log it.
      console.error("Error sending diagnostic message to Telegram:", e.message);
    }

    // Return a simple OK response to the original caller (Telegram).
    return new Response('OK', { status: 200 });
  },
};