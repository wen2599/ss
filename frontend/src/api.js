
import axios from 'axios';
import { store } from './store';

// Create an Axios instance
const apiClient = axios.create({
  // Use a relative URL to proxy requests through the same domain
  // This avoids CORS issues by sending requests to /api/... on the frontend domain
  baseURL: '/api',
  headers: {
    'Content-Type': 'application/json',
  },
});

// Request interceptor to add the JWT token to the headers
apiClient.interceptors.request.use(
  (config) => {
    const token = store.state.token;
    if (token) {
      config.headers['Authorization'] = `Bearer ${token}`;
    }
    return config;
  },
  (error) => {
    return Promise.reject(error);
  }
);

export default apiClient;
