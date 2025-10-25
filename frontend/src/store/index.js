import { reactive, readonly } from 'vue';
import api from '../api'; // Import the api utility

// --- State ---
const state = reactive({
  isAuthenticated: false,
  username: null,
  token: localStorage.getItem('token') || null,
});

// --- Getters ---
const getters = {
  // You can add computed properties here if needed
};

// --- Actions ---
const actions = {
  async login(email, password) {
    const response = await api.post('/login', { email, password });
    if (response && response.data && response.data.token) {
      state.isAuthenticated = true;
      state.username = response.data.user.username;
      state.token = response.data.token;
      localStorage.setItem('token', response.data.token);
      localStorage.setItem('username', response.data.user.username);
      api.defaults.headers.common['Authorization'] = `Bearer ${response.data.token}`;
    } else {
      throw new Error(response.data.message || 'Login failed');
    }
  },

  async register(username, email, password) {
    const response = await api.post('/register', { username, email, password });
    if (!response || response.status !== 201) {
      throw new Error(response.data.message || 'Registration failed');
    }
  },

  logout() {
    state.isAuthenticated = false;
    state.username = null;
    state.token = null;
    localStorage.removeItem('token');
    localStorage.removeItem('username');
    delete api.defaults.headers.common['Authorization'];
    // Optionally, notify the backend to invalidate the token
  },

  checkAuth() {
    const token = localStorage.getItem('token');
    const username = localStorage.getItem('username');
    if (token && username) {
      state.isAuthenticated = true;
      state.username = username;
      state.token = token;
      api.defaults.headers.common['Authorization'] = `Bearer ${token}`;
    } else {
        this.logout();
    }
  },
};

// --- Store Export ---
export const store = {
  state: readonly(state),
  getters,
  actions,
};
