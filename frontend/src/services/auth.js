import apiClient from './api';

const authService = {
  user: null,

  async register(user) {
    const response = await apiClient.post('/api/register.php', {
      email: user.email,
      password: user.password
    });
    this.user = response.data.user;
    return response;
  },

  async login(user) {
    const response = await apiClient.post('/api/login.php', {
      email: user.email,
      password: user.password
    });
    this.user = response.data.user;
    return response;
  },

  isLoggedIn() {
    return document.cookie.includes('PHPSESSID');
  },

  async logout() {
    await apiClient.post('/api/logout.php');
    this.user = null;
    window.location.reload();
  },

  getCurrentUser() {
    return this.user;
  }
};

export default authService;
