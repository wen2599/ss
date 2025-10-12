import axios from 'axios';

const apiClient = axios.create({
    baseURL: '/api', // The backend is proxied to /api
    withCredentials: true, // This is crucial for sending session cookies
});

export const api = {
    /**
     * Logs in a user.
     * @param {string} email - The user's email.
     * @param {string} password - The user's password.
     * @returns {Promise<object>} The server response.
     */
    login: (email, password) => {
        return apiClient.post('/login.php', { email, password });
    },

    /**
     * Registers a new user.
     * @param {string} email - The user's email.
     * @param {string} password - The user's password.
     * @returns {Promise<object>} The server response.
     */
    register: (email, password) => {
        return apiClient.post('/register.php', { email, password });
    },

    /**
     * Logs out the current user.
     * @returns {Promise<object>} The server response.
     */
    logout: () => {
        return apiClient.post('/logout.php');
    },

    /**
     * Checks if the user is currently authenticated.
     * @returns {Promise<object>} The server response with auth status.
     */
    checkAuth: () => {
        return apiClient.get('/check_auth.php');
    },

    /**
     * Fetches the emails for the logged-in user.
     * @returns {Promise<object>} The server response with the list of emails.
     */
    getEmails: () => {
        return apiClient.get('/get_emails.php');
    },
};
