const API_BASE_URL = 'http://localhost:8000'; // Adjust this to your actual backend URL

/**
 * A helper function to handle fetch requests and responses.
 * @param {string} url - The URL to fetch.
 * @param {object} options - The options for the fetch request.
 * @returns {Promise<any>} - The JSON response from the server.
 * @throws {Error} - Throws an error if the network response is not ok.
 */
const fetchJson = async (url, options = {}) => {
    const response = await fetch(url, {
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
 * Checks if an email is authorized for registration.
 * @param {string} email - The email to check.
 * @returns {Promise<{is_authorized: boolean}>} - The authorization status.
 */
export const checkEmailAuthorization = (email) => {
    return fetchJson(`${API_BASE_URL}/check_email.php`, {
        method: 'POST',
        body: JSON.stringify({ email }),
    });
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

export const getEmails = () => {
    return fetchJson(`${API_BASE_URL}/get_emails.php`);
};

export const getEmailById = (id) => {
    return fetchJson(`${API_BASE_URL}/get_emails.php?id=${id}`);
};
