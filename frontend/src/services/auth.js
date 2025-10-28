import api from './api';

const authService = {
  user: null,

  async register(user) {
    const response = await api.post('api.php', {
      action: 'register',
      username: user.username,
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
    const response = await api.post('api.php', {
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

  async checkSession() {
    try {
      const response = await api.post('api.php', { action: 'check_session' });
      if (response.data.loggedIn) {
        this.user = response.data.user;
        localStorage.setItem('user_id', this.user.id);
        return true;
      } else {
        this.user = null;
        localStorage.removeItem('user_id');
        return false;
      }
    } catch (error) {
      this.user = null;
      localStorage.removeItem('user_id');
      return false;
    }
  },

  isLoggedIn() {
    return localStorage.getItem('user_id') !== null;
  },

  async logout() {
    await api.post('api.php', {
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
