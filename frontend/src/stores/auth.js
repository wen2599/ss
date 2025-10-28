import { defineStore } from 'pinia';
import * as authService from '../services/auth';
import router from '../router'; // Import router

export const useAuthStore = defineStore('auth', {
  state: () => ({
    token: localStorage.getItem('token') || null,
    user: JSON.parse(localStorage.getItem('user') || 'null'),
  }),

  getters: {
    isLoggedIn: (state) => !!state.token && !!state.user,
    currentUser: (state) => state.user,
  },

  actions: {
    setAuthentication(token, user) {
      this.token = token;
      this.user = user;
      localStorage.setItem('token', token);
      localStorage.setItem('user', JSON.stringify(user));
    },

    async login(credentials) {
      try {
        const response = await authService.login(credentials);
        if (response.success && response.token && response.user) {
          this.setAuthentication(response.token, response.user);
          return true;
        } else {
          throw new Error(response.message || 'Login failed with invalid data.');
        }
      } catch (error) {
        console.error('Store login action failed:', error);
        throw error;
      }
    },

    async register(userData) {
      try {
        const response = await authService.register(userData);
        if (response.success) {
          return true; // Indicate success
        } else {
          throw new Error(response.message || 'Registration failed with invalid data.');
        }
      } catch (error) {
        console.error('Store register action failed:', error);
        throw error;
      }
    },

    logout() {
      this.token = null;
      this.user = null;
      localStorage.removeItem('token');
      localStorage.removeItem('user');
      router.push('/login');
    },

    async checkSession() {
      if (!this.token) {
        return;
      }
      try {
        const response = await authService.checkSession();
        if (!response.loggedIn) {
          this.logout();
        }
      } catch (error) {
        // The api.js interceptor will likely catch 401s and log out,
        // but we'll do it here just in case.
        this.logout();
      }
    },
  },
});
