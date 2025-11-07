import axios from 'axios';

// Use a relative path for the API base URL.
// This works for both local development (via Vite proxy) and production.
const API_BASE_URL = '/api';

const api = axios.create({
    baseURL: API_BASE_URL,
});

export default api;
