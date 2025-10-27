import api from './api';

const authService = {
  user: null,

  async register(user) {
    const response = await api.post('/auth.php', {
      action: 'register',
      email: user.email,
      password: user.password
    });
    this.user = response.data.user;
    if (this.user && this.user.id) {
      localStorage.setItem('user_id', this.user.id);
    }
    return response;
  },

  async login(user) {
    const response = await api.post('/auth.php', {
      action: 'login',
      email: user.email,
      password: user.password
    });
    this.user = response.data.user;
    if (this.user && this.user.id) {
      localStorage.setItem('user_id', this.user.id);
    }
    return response;
  },

  isLoggedIn() {
    return localStorage.getItem('user_id') !== null && document.cookie.includes('PHPSESSID');
  },

  async logout() {
    await api.post('/auth.php', {
      action: 'logout'
    });
    this.user = null;
    localStorage.removeItem('user_id');
    window.location.href = '/login';
  },

  getCurrentUser() {
    return this.user;
  }
};

export default authService;
