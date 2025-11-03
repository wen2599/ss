export default {
  async email(message, env, ctx) {
    const emailData = {
      from: message.from,
      to: message.to,
      headers: Object.fromEntries(message.headers),
      raw: await new Response(message.raw).text(),
    };

    // Send email data to your backend
    await fetch("https://wenge.cloudns.ch/api/store_email", {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
      },
      body: JSON.stringify(emailData),
    });
  },
};