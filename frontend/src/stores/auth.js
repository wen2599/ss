import { defineStore } from 'pinia';
import apiClient from '../api';
import router from '../router'; // Import router to handle redirects

export const useAuthStore = defineStore('auth', {
  state: () => ({
    isAuthenticated: false,
    username: null,
    authCheckCompleted: false, // New state to track if the initial auth check is done
    isLoading: false, // To track loading state for login/register
  }),
  getters: {
    // isAuthenticated: (state) => state.isAuthenticated,
    // username: (state) => state.username,
  },
  actions: {
    async checkAuth() {
      try {
        const response = await apiClient.get('/check-auth');
        if (response.data.status === 'success' && response.data.data.isLoggedIn) {
          this.isAuthenticated = true;
          this.username = response.data.data.username;
        } else {
          this.isAuthenticated = false;
          this.username = null;
        }
        return { success: true };
      } catch (error) {
        console.error('Error checking authentication:', error);
        this.isAuthenticated = false;
        this.username = null;
        return { success: false };
      } finally {
        this.authCheckCompleted = true;
      }
    },

    async login(email, password) {
      this.isLoading = true;
      try {
        const response = await apiClient.post('/login', { email, password });
        // Assuming a successful login always returns a 2xx status and the user data.
        this.isAuthenticated = true;
        this.username = response.data.data.username; // Use username from response

        // Let the component handle the redirect. The store's job is to manage state.
        // router.push('/lottery');

        return { success: true, message: response.data.message };

      } catch (error) {
        console.error('Login error:', error);
        // Specific handling for 401 might be done by response interceptor in api.js
        return { success: false, message: error.response?.data?.message || 'An error occurred during login.' };
      } finally {
        this.isLoading = false;
      }
    },

    async register(email, password, telegramChatId = null, telegramUsername = null) {
      this.isLoading = true;
      try {
        const response = await apiClient.post('/register', { email, password, telegram_chat_id: telegramChatId, telegram_username: telegramUsername });
        // After registration, the backend now automatically logs the user in.
        // Let's update the state accordingly.
        this.isAuthenticated = true;
        this.username = email;

        // Let the component decide what to do next (e.g., close modal, show message).
        return { success: true, message: response.data.message };
      } catch (error) {
        console.error('Registration error:', error);
        return { success: false, message: error.response?.data?.message || 'An error occurred during registration.' };
      } finally {
        this.isLoading = false;
      }
    },

    async logout() {
      try {
        await apiClient.post('/logout');
        this.isAuthenticated = false;
        this.username = null;
        router.push('/'); // Redirect to home page after logout
      } catch (error) {
        console.error('Logout error:', error);
      }
    },
  },
});
