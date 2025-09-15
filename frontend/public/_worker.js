export default {
  async email(message, env, ctx) {
    const from = message.from;
    const to = message.to;
    const subject = message.headers.get('subject');
    const body = await new Response(message.raw).text();

    const bet_text = body; // Assuming the entire email body is the bet text

    // TODO: Replace with your actual backend URL
    const backendUrl = 'https://your_domain.com/api/place_bet_from_email.php';

    const response = await fetch(backendUrl, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        // TODO: Set the WORKER_SECRET environment variable in your Cloudflare Worker settings
        // Go to your Worker > Settings > Variables > Add variable
        'X-Worker-Secret': env.WORKER_SECRET,
      },
      body: JSON.stringify({
        from_email: from,
        bet_text: bet_text,
      }),
    });

    if (!response.ok) {
      // If the API call fails, you might want to forward the email to a backup address
      // or handle the error in some other way.
      console.error(`Failed to place bet from email: ${response.status} ${response.statusText}`);
    }
  },
};
