
const API_BASE_URL = 'https://wenge.cloudns.ch';

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

