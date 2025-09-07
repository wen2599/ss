const API_BASE_URL = 'https://wenxiuxiu.eu.org/api';

// We need to handle credentials (cookies) for session management
async function request(endpoint, method = 'GET', body = null) {
    const url = `${API_BASE_URL}/api.php?endpoint=${endpoint}`;
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
    try {
        const response = await fetch(url, options);
        // We can't always expect JSON, especially on errors.
        const text = await response.text();
        try {
            return JSON.parse(text);
        } catch (e) {
            // If parsing fails, it might be a server error page.
            console.error("Failed to parse JSON:", text);
            return { success: false, message: "Server returned non-JSON response." };
        }
    } catch (error) {
        console.error(`API request failed to endpoint: ${endpoint}`, error);
        return { success: false, message: error.message };
    }
}

// --- Auth Endpoints ---
export const register = (phone, password) => request('register', 'POST', { phone, password });
export const login = (phone, password) => request('login', 'POST', { phone, password });
export const logout = () => request('logout', 'POST');
export const checkSession = () => request('check_session');
export const findUser = (phone) => request(`find_user&phone=${phone}`);
export const transferPoints = (recipientId, amount) => request('transfer_points', 'POST', { recipientId, amount });


// --- Game Endpoints ---
export const createRoom = () => request('create_room', 'POST');
export const joinRoom = (roomId) => request('join_room', 'POST', { roomId });
// getRoomState still needs the current player's ID to know which hand to return
export const getRoomState = (roomId, playerId) => request(`get_room_state&roomId=${roomId}&playerId=${playerId}`);
export const startGame = (roomId) => request('start_game', 'POST', { roomId });

// The backend now gets the player ID from the session for submitHand
export const submitHand = (gameId, front, middle, back) => {
    return request('submit_hand', 'POST', {
        gameId,
        front,
        middle,
        back
    });
};
