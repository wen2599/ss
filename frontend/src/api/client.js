// API Client
// A centralized module for all frontend-to-backend communications.

// Use Vite's environment variables to define the API base URL.
// This allows for different base URLs in development vs. production.
// VITE_API_BASE_URL should be defined in a .env file at the project root.
// Example: VITE_API_BASE_URL=/api
const API_BASE_URL = import.meta.env.VITE_API_BASE_URL || '';

/**
 * A helper function to handle fetch responses.
 * It checks for a successful response and parses the JSON body.
 * Throws an error if the network response is not ok or if the API indicates a failure.
 * @param {Response} response - The response object from a fetch call.
 * @returns {Promise<any>} - The JSON data from the response.
 */
async function handleResponse(response) {
  if (!response.ok) {
    // Handle HTTP errors like 404 or 500
    throw new Error(`Network response was not ok, status: ${response.status}`);
  }

  const data = await response.json();

  if (!data.success) {
    // Handle application-level errors (e.g., { success: false, error: '...' })
    throw new Error(data.error || 'An unknown API error occurred.');
  }

  return data;
}

/**
 * Fetches the list of all lottery results.
 * @returns {Promise<Array>} A promise that resolves to the array of lottery results.
 */
export async function getLotteryResults() {
  const response = await fetch(`${API_BASE_URL}/get_lottery_results`);
  const data = await handleResponse(response);
  return data.results;
}

/**
 * Fetches game data, including the color map for lottery numbers.
 * @returns {Promise<Object>} A promise that resolves to the game data object.
 */
export async function getGameData() {
  const response = await fetch(`${API_BASE_URL}/get_game_data`);
  const data = await handleResponse(response);
  return data;
}

/**
 * Fetches all bills for the currently authenticated user.
 * Requires credentials to be included for session handling.
 * @returns {Promise<Array>} A promise that resolves to the array of user bills.
 */
export async function getBills() {
  const response = await fetch(`${API_BASE_URL}/get_bills`, {
    credentials: 'include',
  });
  const data = await handleResponse(response);
  return data.bills;
}

/**
 * Deletes a specific bill by its ID.
 * Requires credentials.
 * @param {number} billId - The ID of the bill to delete.
 * @returns {Promise<Object>} A promise that resolves to the success response.
 */
export async function deleteBill(billId) {
  const response = await fetch(`${API_BASE_URL}/delete_bill`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ bill_id: billId }),
    credentials: 'include',
  });
  return await handleResponse(response);
}

/**
 * Updates the settlement notes for a specific slip within a bill.
 * Requires credentials.
 * @param {number} billId - The ID of the bill.
 * @param {number} slipIndex - The index of the slip to update.
 * @param {string} settlementText - The new settlement text.
 * @returns {Promise<Object>} A promise that resolves to the success response.
 */
export async function updateSettlement({ billId, slipIndex, settlementText }) {
  const response = await fetch(`${API_BASE_URL}/update_settlement`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({
      bill_id: billId,
      slip_index: slipIndex,
      settlement_text: settlementText,
    }),
    credentials: 'include',
  });
  return await handleResponse(response);
}

// You can add other API functions here, for example:
// export async function login(username, password) { ... }
// export async function logout() { ... }