import axios from 'axios';

const authService = {
  register(user) {
    return axios.post('/api/register.php', {
      email: user.email,
      password: user.password
    });
  },
  login(user) {
    return axios.post('/api/login.php', {
      email: user.email,
      password: user.password
    });
  },
  isLoggedIn() {
    return !!localStorage.getItem('authToken');
  },
  logout() {
    localStorage.removeItem('authToken');
    window.location.reload();
  }
};

export default authService;
