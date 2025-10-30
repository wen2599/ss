/**
 * frontend/src/api.js
 * 
 * This file centralizes all interactions with the backend API.
 * It exports a set of functions, each corresponding to a specific API endpoint.
 * This approach keeps the component logic clean and makes API management straightforward.
 */

// Use environment variables to determine the API base URL.
// For development, it points to the Vite proxy. For production, it points to the live server.
const API_BASE_URL = import.meta.env.DEV ? '/api' : 'https://wenge.cloudns.ch';

/**
 * A generic fetch helper to reduce boilerplate code and centralize error handling.
 * @param {string} endpoint - The API endpoint to call (e.g., '/emails').
 * @param {object} [options={}] - Optional fetch options (method, headers, body).
 * @returns {Promise<any>} A promise that resolves to the JSON response data.
 * @throws {Error} Throws an error if the network response is not ok.
 */
const fetchApi = async (endpoint, options = {}) => {
  const url = `${API_BASE_URL}${endpoint}`;
  
  const defaultHeaders = {
    'Content-Type': 'application/json',
  };

  const config = {
    ...options,
    headers: {
      ...defaultHeaders,
      ...options.headers,
    },
  };

  try {
    const response = await fetch(url, config);

    if (!response.ok) {
      // Try to parse error message from response body, otherwise use status text
      const errorData = await response.json().catch(() => null);
      const errorMessage = errorData?.message || response.statusText;
      throw new Error(`API Error: ${errorMessage} (Status: ${response.status})`);
    }

    // If the response has content, parse it as JSON, otherwise return null.
    const contentType = response.headers.get("content-type");
    if (contentType && contentType.indexOf("application/json") !== -1) {
      return await response.json();
    }
    return null; // Handle 204 No Content responses

  } catch (error) {
    console.error(`Error calling endpoint ${endpoint}:`, error);
    // Re-throw the error to be caught by the calling component (e.g., in a useEffect hook).
    throw error;
  }
};

// --- API Functions ---

/**
 * Fetches the list of numbers.
 * Corresponds to GET /numbers.
 * @returns {Promise<Array<Object>>} A promise that resolves to an array of number objects.
 */
export const getNumbers = () => fetchApi('/numbers');

/**
 * Fetches the list of all emails.
 * Corresponds to GET /emails.
 * @returns {Promise<Array<Object>>} A promise that resolves to an array of email objects.
 */
export const getEmails = () => fetchApi('/emails');

/**
 * Fetches a single email by its ID.
 * Corresponds to GET /emails/{id}.
 * @param {number|string} id - The ID of the email to fetch.
 * @returns {Promise<Object>} A promise that resolves to the detailed email object.
 */
export const getEmailById = (id) => fetchApi(`/emails/${id}`);

/**
 * Registers a new user.
 * Corresponds to POST /register.
 * @param {string} email - The user's email address.
 * @returns {Promise<Object>} A promise that resolves to the server's response.
 */
export const registerUser = (email) => fetchApi('/register', {
  method: 'POST',
  body: JSON.stringify({ email }),
});

/**
 * Sends a new email.
 * Note: This endpoint might be protected or used internally.
 * Corresponds to POST /emails.
 * @param {Object} emailData - The email data to be sent.
 * @returns {Promise<Object>} A promise that resolves to the server's response.
 */
export const postEmail = (emailData) => fetchApi('/emails', {
  method: 'POST',
  body: JSON.stringify(emailData),
});
