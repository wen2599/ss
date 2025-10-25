import axios from 'axios';
// import { store } from './store'; // Removed direct import of old store
import { useAuthStore } from './stores/auth'; // Import new Pinia store

// Create an Axios instance
const apiClient = axios.create({
  baseURL: '/api',
  headers: {
    'Content-Type': 'application/json',
  },
  withCredentials: true,
});

// Request interceptor to add the JWT token to the headers (if applicable)
apiClient.interceptors.request.use(
  (config) => {
    // For session-based authentication, cookies handle the session ID automatically.
    // If JWTs were used (e.g., from localStorage), this interceptor would add the token.
    // const authStore = useAuthStore(); // Can't call useAuthStore() outside of setup/component context directly
    // const token = authStore.token; // If a token were stored in Pinia
    // if (token) {
    //   config.headers['Authorization'] = `Bearer ${token}`;
    // }
    return config;
  },
  (error) => {
    return Promise.reject(error);
  }
);

// Response interceptor to handle common errors
apiClient.interceptors.response.use(
  (response) => response,
  (error) => {
    if (error.response) {
      // Handle 401 Unauthorized errors
      if (error.response.status === 401) {
        console.log('401 Unauthorized: Logging out user.');
        // Access Pinia store to call logout action
        const authStore = useAuthStore();
        authStore.logout(); 
      }
    }
    return Promise.reject(error);
  }
);

export default apiClient;
