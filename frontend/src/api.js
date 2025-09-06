// api.js

// The base URL is now relative, so requests are sent to the same origin
// as the frontend. The service worker will intercept requests starting with /api/.
const API_BASE_URL = '';

// Function to create a new game room
export const createRoom = async () => {
  try {
    const response = await fetch(`${API_BASE_URL}/api/create_room`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
      },
    });

    if (!response.ok) {
      throw new Error(`HTTP error! status: ${response.status}`);
    }

    return await response.json();
  } catch (error) {
    console.error('Error creating room:', error);
    throw error;
  }
};

// Function to join an existing game room
export const joinRoom = async (roomId) => {
  try {
    const response = await fetch(`${API_BASE_URL}/api/join_room`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ roomId }),
    });

    return await response.json();
  } catch (error) {
    console.error('Error joining room:', error);
    throw error;
  }
};

// Function to play cards
export const playCards = async (gameId, playerId, cards) => {
  try {
    const response = await fetch(`${API_BASE_URL}/api/play_cards`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
      },
      body: JSON.stringify({ game_id: gameId, player_id: playerId, cards: cards }),
    });
    return await response.json();
  } catch (error) {
    console.error('Error playing cards:', error);
    throw error;
  }
};

// Function to get the current state of a game room
export const getRoomState = async (roomId, playerId) => {
  try {
    const response = await fetch(`${API_BASE_URL}/api/get_room_state?roomId=${roomId}&playerId=${playerId}`, {
      method: 'GET',
      headers: {
        'Content-Type': 'application/json',
      },
    });

    const data = await response.json();
    return data;
  } catch (error) {
    console.error('Error getting room state:', error);
    throw error;
  }
};
