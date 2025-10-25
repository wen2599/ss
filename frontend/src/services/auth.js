import apiClient from './api';

const authService = {
  register(user) {
    return apiClient.post('/api/register.php', {
      email: user.email,
      password: user.password
    });
  },
  login(user) {
    return apiClient.post('/api/login.php', {
      email: user.email,
      password: user.password
    });
  },
  isLoggedIn() {
    return document.cookie.includes('PHPSESSID');
  },
  logout() {
    return apiClient.post('/api/logout.php').then(() => {
      window.location.reload();
    });
  }
};

export default authService;
