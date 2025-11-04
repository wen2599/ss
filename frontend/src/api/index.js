// frontend/src/api/index.js

const API_PREFIX = '/api'; // 由 _worker.js 代理

const handleResponse = async (response) => {
    if (!response.ok) {
        const errorData = await response.json().catch(() => ({ message: '发生未知错误' }));
        throw new Error(errorData.message || `HTTP error! status: ${response.status}`);
    }
    return response.json();
};

export const registerUser = async (email, password) => {
    const response = await fetch(`${API_PREFIX}/register`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ email, password }),
    });
    return handleResponse(response);
};

export const loginUser = async (email, password) => {
    const response = await fetch(`${API_PREFIX}/login`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ email, password }),
    });
    return handleResponse(response);
};

export const getLatestLotteryNumber = async () => {
    const response = await fetch(`${API_PREFIX}/lottery/latest`);
    return handleResponse(response);
};