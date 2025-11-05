const API_BASE_URL = import.meta.env.DEV ? '/api' : '/api';

const handleResponse = async (response) => {
    if (!response.ok) {
        const errorData = await response.json().catch(() => ({ error: 'An unknown error occurred' }));
        throw new Error(errorData.error || `HTTP error! status: ${response.status}`);
    }
    return response.json();
};

// Helper to get the auth token
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
