const API_BASE_URL = import.meta.env.DEV ? '/api' : '/api';

const handleResponse = async (response) => {
    if (!response.ok) {
        const errorData = await response.json().catch(() => ({ error: 'An unknown error occurred' }));
        throw new Error(errorData.error || `HTTP error! status: ${response.status}`);
    }
    return response.json();
};

// Helper to get the auth token (still present for consistency, though not used for lottery results)
const getAuthHeaders = () => {
    const token = localStorage.getItem('authToken');
    return token ? { 'Authorization': `Bearer ${token}` } : {};
};

export const getEmails = () => {
    return fetch(`${API_BASE_URL}?action=get_emails`, {
        headers: getAuthHeaders(),
    }).then(handleResponse);
};

export const getEmailBody = (id) => {
    return fetch(`${API_BASE_URL}?action=get_email_body&id=${id}`, {
        headers: getAuthHeaders(),
    }).then(handleResponse);
};

export const getResults = (type = null, limit = 20) => {
    let url = `${API_BASE_URL}?action=get_lottery_results`;
    const params = new URLSearchParams({ limit });
    if (type) {
        params.append('type', type);
    }
    url = `${url}&${params.toString()}`;

    return fetch(url, {
        method: 'GET',
        headers: {
            'Content-Type': 'application/json',
        },
    }).then(handleResponse);
};
