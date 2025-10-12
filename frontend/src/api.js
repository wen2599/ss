import axios from 'axios';

const API_URL = 'http://localhost:5000/api';

const apiClient = axios.create({
    baseURL: API_URL,
    withCredentials: true, // Send cookies with requests
});

export const api = {
    getEmails: () => apiClient.get('/emails'),
    // Add other non-auth API calls here if needed
};
