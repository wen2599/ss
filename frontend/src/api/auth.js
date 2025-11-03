const API_BASE_URL = '/api'; // _worker.js 会代理这个路径

export const registerUser = async (email, password) => {
    const response = await fetch(`${API_BASE_URL}/index.php?action=register`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ email, password }),
    });
    return response.json();
};

export const loginUser = async (email, password) => {
    const response = await fetch(`${API_BASE_URL}/index.php?action=login`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ email, password }),
    });
    return response.json();
};