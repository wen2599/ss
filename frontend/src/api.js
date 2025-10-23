import axios from 'axios';

const apiClient = axios.create({
  baseURL: 'https://wenge.cloudns.ch/api', // Changed to absolute URL
  headers: {
    'Content-Type': 'application/json',
  },
});

export default apiClient;
