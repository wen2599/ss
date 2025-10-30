// frontend/src/services/api.js

// Define the base URL for the backend API.
// It's important that the backend server has CORS configured to allow requests from the frontend's origin.
const API_BASE_URL = 'https://wenge.cloudns.ch/api';

/**
 * Fetches the latest lottery draws from the backend.
 *
 * @returns {Promise<Array>} A promise that resolves to an array of lottery draw objects.
 * @throws {Error} Throws an error if the network response is not ok.
 */
export const getLatestDraws = async () => {
  try {
    const response = await fetch(`${API_BASE_URL}/get_latest_draws.php`);

    if (!response.ok) {
      // If the server response is not successful (e.g., 404, 500), throw an error.
      throw new Error(`Network response was not ok: ${response.statusText}`);
    }

    const data = await response.json();

    if (!data.success) {
        // If the API returns a success: false flag, throw an error with the message.
        throw new Error(data.message || 'An API error occurred.');
    }

    return data.data;

  } catch (error) {
    // Log the error to the console for debugging purposes.
    console.error("Failed to fetch lottery draws:", error);
    // Re-throw the error to be handled by the calling component.
    throw error;
  }
};
