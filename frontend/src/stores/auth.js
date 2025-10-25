// frontend/src/stores/auth.js
import { defineStore } from 'pinia';
import authService from '@/services/auth.js';

export const useAuthStore = defineStore('auth', {
  state: () => ({
    isLoggedIn: document.cookie.includes('PHPSESSID'),
  }),
  actions: {
    async login(email, password) {
      const response = await authService.login({ email, password });
      this.isLoggedIn = true;
      return response;
    },
    async register(email, password) {
        const response = await authService.register({ email, password });
        // After successful registration, log the user in
        this.isLoggedIn = true;
        return response;
    },
    async logout() {
      await authService.logout();
      this.isLoggedIn = false;
    },
    checkAuth() {
        this.isLoggedIn = document.cookie.includes('PHPSESSID');
    }
  },
});
