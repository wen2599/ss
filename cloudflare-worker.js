export default {
  async fetch(request, env, ctx) {
    // 1. Security Check
    if (request.method !== 'POST') {
      return new Response('Expected POST', { status: 405 });
    }

    const authHeader = request.headers.get('Authorization');
    if (!authHeader || authHeader !== `Bearer ${env.WORKER_SECRET}`) {
      return new Response('Unauthorized', { status: 403 });
    }

    // 2. Get the email content from the request body
    const { content } = await request.json();
    if (!content) {
      return new Response('Missing "content" in request body', { status: 400 });
    }

    // 3. Define the AI prompt
    // This prompt instructs the AI on how to behave and what format to return.
    const messages = [
      {
        role: 'system',
        content: `You are an expert in parsing betting slips. Your task is to analyze the provided text and extract betting information into a structured JSON format. The output should be a JSON object containing a single key: "slips". The "slips" key should be an array of objects, where each object represents a distinct betting group and has two keys: "numbers" (an array of strings) and "cost_per_number" (an integer).

Here are some examples of how to parse different formats:
- "36,48各30#" -> { "slips": [{ "numbers": ["36", "48"], "cost_per_number": 30 }] }
- "04.16.28各5块" -> { "slips": [{ "numbers": ["04", "16", "28"], "cost_per_number": 5 }] }
- "40x10元" -> { "slips": [{ "numbers": ["40"], "cost_per_number": 10 }] }
- "鼠,鸡数各二十元" -> This is a zodiac bet, not a number bet. Ignore it completely.
- "澳门: 39, 30, 各5元" -> { "slips": [{ "numbers": ["39", "30"], "cost_per_number": 5 }] }

Only extract number-based bets. Ignore any text that is not a clear bet, and ignore all zodiac-based bets (e.g., "鼠,鸡数各二十元"). If no valid number bets are found, return an empty "slips" array: { "slips": [] }. Ensure your final output is only the JSON object, with no other text or explanations.`
      },
      {
        role: 'user',
        content: content,
      },
    ];

    // 4. Call the Cloudflare AI Model
    try {
      const response = await env.AI.run('@cf/meta/llama-3-8b-instruct', {
        messages,
        response_format: { type: 'json_object' },
      });

      // 5. Return the AI's response
      // The AI should return a JSON string, so we parse it before sending it back.
      const aiResponse = JSON.parse(response.response);
      return new Response(JSON.stringify(aiResponse), {
        headers: { 'Content-Type': 'application/json' },
      });

    } catch (e) {
      // 6. Error Handling
      return new Response(JSON.stringify({ error: e.message }), {
        status: 500,
        headers: { 'Content-Type': 'application/json' },
      });
    }
  },
};