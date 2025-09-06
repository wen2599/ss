const API_BASE_URL = 'https://wenxiuxiu.eu.org/api';

async function request(endpoint, method = 'GET', body = null) {
    const url = `${API_BASE_URL}/api.php?endpoint=${endpoint}`;
    const options = {
        method,
        headers: {
            'Content-Type': 'application/json',
        },
    };
    if (body) {
        options.body = JSON.stringify(body);
    }
    try {
        const response = await fetch(url, options);
        if (!response.ok) {
            const errorData = await response.json().catch(() => ({ message: 'HTTP error with no JSON body' }));
            throw new Error(errorData.message || `HTTP error! status: ${response.status}`);
        }
        return await response.json();
    } catch (error) {
        console.error(`API request failed to endpoint: ${endpoint}`, error);
        return { success: false, message: error.message };
    }
}

export const createRoom = () => request('create_room', 'POST');
export const joinRoom = (roomId) => request('join_room', 'POST', { roomId });
export const getRoomState = (roomId, playerId) => request(`get_room_state&roomId=${roomId}&playerId=${playerId}`);
export const startGame = (roomId) => request('start_game', 'POST', { roomId });

export const submitHand = (gameId, playerId, front, middle, back) => {
    return request('submit_hand', 'POST', {
        gameId,
        playerId,
        front,
        middle,
        back
    });
};
