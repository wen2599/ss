/**
 * API client for the Thirteen card game.
 * This module provides functions to interact with the backend API.
 */
const API_BASE_URL = '/api';

/**
 * A generic request function to interact with the backend API.
 * @param {string} endpoint The API endpoint to call.
 * @param {string} [method='GET'] The HTTP method to use.
 * @param {object|null} [body=null] The request body for POST requests.
 * @returns {Promise<object>} The JSON response from the server.
 */
async function request(endpoint, method = 'GET', body = null) {
    // Note: The production code uses 'api.php' but for our refactored version, we'll use the new index.php router
    const url = `${API_BASE_URL}/index.php?endpoint=${endpoint}`;
    const options = {
        method,
        headers: {
            'Content-Type': 'application/json',
        },
        credentials: 'include', // Important for sending session cookies
    };
    if (body) {
        options.body = JSON.stringify(body);
    }

    const response = await fetch(url, options);
    const text = await response.text();
    let data;
    try {
        data = JSON.parse(text);
    } catch (e) {
        console.error("Failed to parse JSON:", text);
        throw new Error("Server returned non-JSON response.");
    }

    if (!data.success) {
        throw new Error(data.message || 'An unknown error occurred.');
    }

    return data;
}

// --- Auth Endpoints ---
export const register = (phone, password) => request('register', 'POST', { phone, password });
export const login = (phone, password) => request('login', 'POST', { phone, password });
export const logout = () => request('logout', 'POST');
export const checkSession = () => request('check_session');
export const findUser = (phone) => request(`find_user&phone=${phone}`);
export const transferPoints = (recipientId, amount) => request('transfer_points', 'POST', { recipientId, amount });


// --- Game Endpoints ---
export const matchmake = (game_mode) => request('matchmake', 'POST', { game_mode });
export const getRoomState = (roomId, stateHash) => {
    let url = `get_room_state&roomId=${roomId}`;
    if (stateHash) {
        url += `&lastStateHash=${stateHash}`;
    }
    return request(url);
};
export const startGame = (roomId) => request('start_game', 'POST', { roomId });

export const submitHand = (gameId, front, middle, back) => {
    return request('submit_hand', 'POST', {
        gameId,
        front,
        middle,
        back
    });
};

// --- Chat Endpoints ---
export const getMessages = (roomId) => request(`get_messages&roomId=${roomId}`);
export const sendMessage = (roomId, message) => request('send_message', 'POST', { roomId, message });

// --- Friends Endpoints ---
export const getFriends = () => request('get_friends');
export const addFriend = (friendId) => request('add_friend', 'POST', { friendId });
export const acceptFriend = (friendId) => request('accept_friend', 'POST', { friendId });

// --- Draw Endpoints ---
export const getDraws = () => request('get_draws');

// --- Bet Endpoints ---
export const getUserBets = () => request('get_user_bets');

// --- Leaderboard Endpoints ---
export const getLeaderboard = () => request('get_leaderboard');
