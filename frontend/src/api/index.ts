// frontend/src/api/index.ts
import axios from 'axios';

const apiClient = axios.create({
  baseURL: import.meta.env.VITE_API_BASE_URL,
  headers: {
    'Content-Type': 'application/json',
  },
});

export const getLatestDraw = () => {
  return apiClient.get('/api/get_latest_draw.php');
};

export const placeBet = (numbers: number[]) => {
  return apiClient.post('/api/place_bet.php', { numbers });
};

export const getMessages = () => {
  return apiClient.get('/api/get_messages.php');
};
