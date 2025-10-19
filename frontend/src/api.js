// 文件: frontend/src/api.js (MODIFIED FOR FRONT CONTROLLER)

const API_BASE_URL = import.meta.env.DEV ? '/api' : '';

/**
 * A helper function to build the correct API endpoint URL.
 * @param {string} endpoint - The name of the endpoint (e.g., 'login_user').
 * @param {URLSearchParams} [params] - Optional URL search parameters.
 * @returns {string} The full API URL.
 */
const buildApiUrl = (endpoint, params = null) => {
    // All requests now go through index.php
    const url = new URL(`${API_BASE_URL}/index.php`, window.location.origin);
    url.searchParams.append('endpoint', endpoint);

    // Append any additional query parameters
    if (params) {
        for (const [key, value] of params.entries()) {
            url.searchParams.append(key, value);
        }
    }
    return url.toString();
};


/**
 * A helper function to handle fetch requests and responses.
 */
const fetchJson = async (url, options = {}) => {
    const response = await fetch(url, {
        credentials: 'include',
        headers: { 'Content-Type': 'application/json', ...options.headers },
        ...options,
    });
    if (!response.ok) {
        const errorData = await response.json().catch(() => ({ error: 'Network response was not ok' }));
        throw new Error(errorData.error || `HTTP error! status: ${response.status}`);
    }
    return response.json();
};

export const registerUser = (userData) => {
    return fetchJson(buildApiUrl('register_user'), {
        method: 'POST',
        body: JSON.stringify(userData),
    });
};

export const loginUser = (credentials) => {
    return fetchJson(buildApiUrl('login_user'), {
        method: 'POST',
        body: JSON.stringify(credentials),
    });
};

export const logoutUser = () => {
    return fetchJson(buildApiUrl('logout_user'), { // Endpoint name adjusted
        method: 'POST',
    });
};

export const checkSession = () => {
    return fetchJson(buildApiUrl('check_session')); // Endpoint name adjusted
};

export const getEmails = () => {
    return fetchJson(buildApiUrl('get_emails'));
};

export const getEmailById = (id) => {
    const params = new URLSearchParams({ id });
    return fetchJson(buildApiUrl('get_emails', params)); // Pass id as a URL parameter
};

export const deleteBill = (id) => {
    const params = new URLSearchParams({ id });
    return fetchJson(buildApiUrl('delete_bill', params), { // Pass id as a URL parameter
        method: 'DELETE',
    });
};

export const processEmailWithAI = (id) => {
    return fetchJson(buildApiUrl('process_email_ai'), {
        method: 'POST',
        body: JSON.stringify({ email_id: id }),
    });
};

export const getLotteryResults = (type = null) => {
    const params = new URLSearchParams();
    if (type) {
        params.append('lottery_type', type);
    }
    return fetchJson(buildApiUrl('get_lottery_results', params));
};
