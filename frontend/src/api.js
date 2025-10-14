
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


export const getEmails = () => {
    return fetchJson(`${API_BASE_URL}/get_emails.php`);
};

export const getEmailById = (id) => {
    return fetchJson(`${API_BASE_URL}/get_emails.php?id=${id}`);
};

