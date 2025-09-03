// api.js

// Base URL for the backend API
const API_BASE_URL = 'https://wenge.cloudns.ch'; // Use the provided backend domain

// Function to create a new game room
export const createRoom = async () => {
  try {
    const response = await fetch(`${API_BASE_URL}/create_room`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
      },
      // Optionally send some initial data, e.g., player name if needed on creation
      // body: JSON.stringify({ playerName: 'Host Player' }),
    });

    if (!response.ok) {
      throw new Error(`HTTP error! status: ${response.status}`);
    }

    return await response.json();
  } catch (error) {
    console.error('Error creating room:', error);
    throw error; // Re-throw the error for handling in the component
  }
};

// Function to join an existing game room
export const joinRoom = async (roomId) => {
  try {
    const response = await fetch(`${API_BASE_URL}/join_room`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ roomId }),
    });

    return await response.json(); // Assuming the backend always returns JSON
  } catch (error) {
    console.error('Error joining room:', error);
    throw error;
  }
};

// Function to play cards
export const playCards = async (gameId, playerId, cards) => {
  try {
    const response = await fetch(`${API_BASE_URL}/play_cards`, {
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
    const response = await fetch(`${API_BASE_URL}/get_room_state?roomId=${roomId}&playerId=${playerId}`, {
      method: 'GET',
      headers: {
        'Content-Type': 'application/json',
      },
    });

    const data = await response.json();
    return data; // Assuming the backend returns the room state within a 'room' key
  } catch (error) {
    console.error('Error getting room state:', error);
    throw error;
  }
};
