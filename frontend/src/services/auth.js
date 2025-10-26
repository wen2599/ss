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
    return response;
  },

  async login(user) {
    const response = await axios.post('/api/auth', {
      action: 'login',
      email: user.email,
      password: user.password
    });
    this.user = response.data.user;
    return response;
  },

  isLoggedIn() {
    // This is a simple check. A more robust solution might involve
    // a dedicated endpoint to verify the session on the server.
    return document.cookie.includes('PHPSESSID');
  },

  async logout() {
    await axios.post('/api/auth', {
      action: 'logout'
    });
    this.user = null;
    window.location.href = '/login'; // Redirect to login
  },

  getCurrentUser() {
    return this.user;
  }
};

export default authService;
