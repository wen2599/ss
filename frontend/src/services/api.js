const API_BASE_URL = import.meta.env.VITE_API_BASE_URL || '';

/**
 * A helper function to handle fetch requests and responses, routing through the worker.
 * @param {string} endpoint The API endpoint to call (e.g., '/login', '/get_bills').
 * @param {RequestInit} options The options for the fetch request.
 * @returns {Promise<any>} The JSON response from the API.
 * @throws {Error} If the network request fails or the API returns an error.
 */
async function apiService(endpoint, options = {}) {
    // The URL is now just the base URL + the endpoint, which the worker will intercept.
    const url = `${API_BASE_URL}${endpoint}`;
    
    const headers = {
        'Content-Type': 'application/json',
        ...options.headers,
    };

    try {
        const response = await fetch(url, { ...options, headers });

        if (!response.ok) {
            const errorData = await response.json().catch(() => ({ error: `Request failed with status ${response.status}` }));
            throw new Error(errorData.error);
        }

        const data = await response.json();
        
        // The worker and backend provide a consistent { success: boolean, ... } structure
        if (data.success) {
            return data;
        } else {
            throw new Error(data.error || 'An unknown API error occurred.');
        }

    } catch (error) {
        console.error(`API service error calling ${endpoint}:`, error.message);
        throw error;
    }
}

// --- Specific API functions ---

// Note: The endpoints are now root paths that the Cloudflare Worker will intercept.
export const getLotteryResults = () => {
    return apiService('/get_lottery_results');
};

export const getGameData = () => {
    return apiService('/get_game_data');
};

export const checkSession = () => {
    return apiService('/check_session', {
        credentials: 'include',
    });
};

export const login = (email, password) => {
    return apiService('/login', {
        method: 'POST',
        credentials: 'include',
        body: JSON.stringify({ email, password }),
    });
};

export const logout = () => {
    return apiService('/logout', {
        method: 'POST',
        credentials: 'include',
    });
};

export const register = (email, password) => {
    return apiService('/register', {
        method: 'POST',
        credentials: 'include',
        body: JSON.stringify({ email, password }),
    });
};

export const getBills = () => {
    return apiService('/get_bills', {
        credentials: 'include',
    });
};
