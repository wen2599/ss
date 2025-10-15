
const API_BASE_URL = ''; // The backend files are in the root directory

/**
 * A helper function to handle fetch requests and responses.
 * @param {string} url - The URL to fetch.
 * @param {object} options - The options for the fetch request.
 * @returns {Promise<any>} - The JSON response from the server.
 * @throws {Error} - Throws an error if the network response is not ok.
 */
const fetchJson = async (url, options = {}) => {
    const response = await fetch(url, {
        credentials: 'include', // Send cookies with all requests
        headers: {
            'Content-Type': 'application/json',
            ...options.headers,
        },
        ...options,
    });

    if (!response.ok) {
        const errorData = await response.json().catch(() => ({ error: 'Network response was not ok' }));
        throw new Error(errorData.error || `HTTP error! status: ${response.status}`);
    }

    return response.json();
};


/**
 * Registers a new user.
 * @param {object} userData - The user data for registration.
 * @returns {Promise<any>} - The response from the server.
 */
export const registerUser = (userData) => {
    return fetchJson(`${API_BASE_URL}/register_user.php`, {
        method: 'POST',
        body: JSON.stringify(userData),
    });
};

/**
 * Logs in a user.
 * @param {object} credentials - The user's login credentials.
 * @param {string} credentials.email - The user's email.
 * @param {string} credentials.password - The user's password.
 * @returns {Promise<any>} - The response from the server, including user data.
 */
export const loginUser = (credentials) => {
    return fetchJson(`${API_BASE_URL}/login_user.php`, {
        method: 'POST',
        body: JSON.stringify(credentials),
    });
};

/**
 * Logs out the current user.
 * @returns {Promise<any>} - The response from the server.
 */
export const logoutUser = () => {
    return fetchJson(`${API_BASE_URL}/logout_user.php`, {
        method: 'POST',
    });
};

export const getEmails = () => {
    return fetchJson(`${API_BASE_URL}/get_emails.php`);
};

export const getEmailById = (id) => {
    return fetchJson(`${API_BASE_URL}/get_emails.php?id=${id}`);
};

/**
 * Deletes a bill by its ID.
 * @param {number} id - The ID of the bill to delete.
 * @returns {Promise<any>} - The response from the server.
 */
export const deleteBill = (id) => {
    return fetchJson(`${API_BASE_URL}/delete_bill.php?id=${id}`, {
        method: 'DELETE',
    });
};

/**
 * Sends an email ID to the backend to be processed by the AI worker.
 * @param {number} id The ID of the email to process.
 * @returns {Promise<any>} The structured data extracted by the AI.
 */
export const processEmailWithAI = (id) => {
    return fetchJson(`${API_BASE_URL}/process_email_ai.php`, {
        method: 'POST',
        body: JSON.stringify({ email_id: id }),
    });
};

/**
 * Fetches the latest lottery results from the backend.
 * @returns {Promise<any>} The latest lottery result.
 */
export const getLotteryResults = (type = null) => {
    const url = new URL(`${API_BASE_URL}/get_lottery_results.php`, window.location.origin);
    if (type) {
        url.searchParams.append('type', type);
    }
    return fetchJson(url.toString());
};
