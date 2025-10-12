import axios from 'axios';

const apiClient = axios.create({
    baseURL: 'https://wenge.cloudns.ch/api', // All requests are now prefixed with /api
    withCredentials: true, // This is crucial for sending session cookies
});

export const api = {
    // Auth
    register: (email, password) => apiClient.post('/register', { email, password }),
    login: (email, password) => apiClient.post('/login', { email, password }),
    logout: () => apiClient.post('/logout'),
    checkAuth: () => apiClient.get('/check_auth'),

    // Emails
    getEmails: () => apiClient.get('/get_emails'),
};
