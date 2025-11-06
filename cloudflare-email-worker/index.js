import PostalMime from 'postal-mime';

export default {
  async email(message, env, ctx) {
    // message.from: sender
    // message.to: recipient
    // message.raw: ReadableStream of the raw email

    // Get raw email content as string
    const rawEmail = await new Response(message.raw).text();

    // Prepare data to send to your backend
    const data = {
      from: message.from,
      raw_content: rawEmail,
    };

    // Send data to your backend API
    try {
      const response = await fetch(env.BACKEND_RECEIVE_URL, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-Auth-Token': env.BACKEND_SECRET_TOKEN, // Secret token for auth
        },
        body: JSON.stringify(data),
      });

      if (!response.ok) {
        // If the backend returned an error, you can forward the email to a failure address
        // or just log it.
        console.error(`Backend returned error: ${response.status} ${response.statusText}`);
        // Optional: Forward to an admin address on failure
        // await message.forward("admin-alerts@yourdomain.com");
      }
    } catch (error) {
      console.error('Failed to send email to backend:', error);
      // Optional: Forward on network failure too
      // await message.forward("admin-alerts@yourdomain.com");
    }
  },
};
