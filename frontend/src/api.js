import axios from 'axios';

const apiClient = axios.create({
  baseURL: 'https://wenge.cloudns.ch/api', // Reverted to original URL without port
  headers: {
    'Content-Type': 'application/json',
  },
});

export default apiClient;
