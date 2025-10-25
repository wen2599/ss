import axios from 'axios';

// Define the absolute base URL for your backend API
const API_URL = 'https://wenge.cloudns.ch/backend/api/';

const authService = {
  register(user) {
    // The second argument to axios.post is the data.
    // The endpoint is now the full URL.
    return axios.post(API_URL + 'register.php', {
      email: user.email,
      password: user.password
    });
  },
  login(user) {
    return axios.post(API_URL + 'login.php', {
      email: user.email,
      password: user.password
    });
  }
};

export default authService;
