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

// --- Auth API ---
export const register = (credentials: object) => {
    return apiClient.post('/api/register.php', credentials);
};

export const login = (credentials: object) => {
    return apiClient.post('/api/login.php', credentials);
};

export const logout = () => {
    return apiClient.post('/api/logout.php');
};

export const checkSession = () => {
    return apiClient.get('/api/check_session.php');
};
