const API_BASE_URL = '/api';

const request = async (endpoint, options = {}) => {
    const url = `${API_BASE_URL}${endpoint}`;
    const token = localStorage.getItem('token');

    const headers = {
        'Content-Type': 'application/json',
        ...options.headers,
    };

    if (token) {
        headers['Authorization'] = `Bearer ${token}`;
    }

    const config = {
        ...options,
        headers,
    };

    try {
        const response = await fetch(url, config);
        const data = await response.json();
        if (!response.ok) {
            throw new Error(data.error || 'API request failed');
        }
        return data;
    } catch (error) {
        console.error('API Error:', error);
        throw error;
    }
};

export const api = {
    register: (data) => request('/users/register', { method: 'POST', body: JSON.stringify(data) }),
    login: (data) => request('/users/login', { method: 'POST', body: JSON.stringify(data) }),
    getWinningNumbers: (limit = 100) => request(`/winning-numbers?limit=${limit}`, { method: 'GET' }),
    getMyBets: () => request('/my-bets', { method: 'GET' }), // Placeholder for future API
};