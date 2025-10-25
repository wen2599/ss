import { defineStore } from 'pinia';
import apiClient from '../api';
import router from '../router'; // Import router to handle redirects

export const useAuthStore = defineStore('auth', {
  state: () => ({
    isAuthenticated: false,
    username: null,
    authCheckCompleted: false, // New state to track if the initial auth check is done
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
      try {
        const response = await apiClient.post('/login', { email, password });
        if (response.data.status === 'success') {
          this.isAuthenticated = true;
          this.username = email;
          router.push('/lottery'); // Redirect to a protected route after login
          return { success: true, message: response.data.message };
        } else {
          return { success: false, message: response.data.message || 'Login failed.' };
        }
      } catch (error) {
        console.error('Login error:', error);
        // Specific handling for 401 might be done by response interceptor in api.js
        return { success: false, message: error.response?.data?.message || 'An error occurred during login.' };
      }
    },

    async register(email, password, telegramChatId = null, telegramUsername = null) {
      try {
        const response = await apiClient.post('/register', { email, password, telegram_chat_id: telegramChatId, telegram_username: telegramUsername });
        if (response.data.status === 'success') {
          // Optionally auto-login or guide user to login after registration
          // this.isAuthenticated = true;
          // this.username = email;
          // router.push('/lottery');
          return { success: true, message: response.data.message };
        } else {
          return { success: false, message: response.data.message || 'Registration failed.' };
        }
      } catch (error) {
        console.error('Registration error:', error);
        return { success: false, message: error.response?.data?.message || 'An error occurred during registration.' };
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
