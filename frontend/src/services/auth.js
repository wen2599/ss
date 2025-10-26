import axios from 'axios';

const authService = {
  user: null,

  async register(user) {
    const response = await axios.post('/register.php', {
      email: user.email,
      password: user.password
    });
    this.user = response.data.user;
    return response;
  },

  async login(user) {
    const response = await axios.post('/login.php', {
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
    await axios.post('/logout.php');
    this.user = null;
    window.location.href = '/login';
  },

  getCurrentUser() {
    return this.user;
  }
};

export default authService;
