// 注意：这里的 API_BASE_URL 是指向我们自己的前端域名
// 因为 _worker.js 会代理请求
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
        if (!response.ok) {
            const errorData = await response.json();
            throw new Error(errorData.error || 'Something went wrong');
        }
        return response.json();
    } catch (error) {
        console.error('API Error:', error);
        throw error;
    }
};

export const api = {
    register: (data) => request('/users/register', { method: 'POST', body: JSON.stringify(data) }),
    login: (data) => request('/users/login', { method: 'POST', body: JSON.stringify(data) }),
    getWinningNumbers: (limit = 100) => request(`/winning-numbers?limit=${limit}`, { method: 'GET' }),
    getMyBets: () => request('/my-bets', { method: 'GET' }),
    // ... 其他 API 调用
};