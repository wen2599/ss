import axios from 'axios';

// Determine the base URL based on the environment
// In a real-world scenario, you would use environment variables for this
const API_BASE_URL = import.meta.env.VITE_API_URL || 'http://localhost/email-bet-processor/backend/api';

const api = axios.create({
    baseURL: API_BASE_URL,
});

export default api;
