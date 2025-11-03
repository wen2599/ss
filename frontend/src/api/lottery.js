const API_BASE_URL = '/api';

export const getLotteryNumbers = async (token) => {
    const response = await fetch(`${API_BASE_URL}/index.php?action=get_numbers`, {
        headers: {
            'Authorization': `Bearer ${token}`
        }
    });
    if (!response.ok) {
        // 如果 token 失效或服务器出错，抛出异常
        throw new Error('Failed to fetch numbers. Please log in again.');
    }
    return response.json();
};