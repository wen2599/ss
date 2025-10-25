import axios from 'axios';

// Define the absolute base URL for your backend API
const API_URL = 'https://wenge.cloudns.ch/api/auth.php';

const authService = {
  register(user) {
    // The second argument to axios.post is the data.
    // The endpoint is now the full URL.
    return axios.post(API_URL + '/register', {
      email: user.email,
      password: user.password
    });
  },
  login(user) {
    return axios.post(API_URL + '/login', {
      email: user.email,
      password: user.password
    });
  },
  logout() {
    return axios.post(API_URL + '/logout');
  },
  isLoggedIn() {
    // Check if the session cookie exists. This is a simple check.
    // A more robust solution might involve a dedicated endpoint to check session status.
    return document.cookie.includes('PHPSESSID');
  }
};

export default authService;
