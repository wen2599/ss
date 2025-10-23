import axios from 'axios';

const apiClient = axios.create({
  baseURL: 'https://wenge.cloudns.ch:10758/api', // Use port 10758 for the backend
  headers: {
    'Content-Type': 'application/json',
  },
});

export default apiClient;
