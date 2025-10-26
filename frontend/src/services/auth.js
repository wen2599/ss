import axios from 'axios';

const authService = {
  user: null,

  async register(user) {
    const response = await axios.post('/api/auth', {
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
    const response = await axios.post('/api/auth', {
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
    // 检查localStorage中是否有user_id，并结合PHP会话cookie检查，确保可靠性
    return localStorage.getItem('user_id') !== null && document.cookie.includes('PHPSESSID');
  },

  async logout() {
    await axios.post('/api/auth', {
      action: 'logout'
    });
    this.user = null;
    localStorage.removeItem('user_id'); // 移除localStorage中的user_id
    window.location.href = '/login';
  },

  getCurrentUser() {
    return this.user;
  }
};

export default authService;
