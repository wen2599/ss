import { reactive, readonly } from 'vue';

// --- State ---
const state = reactive({
  isAuthenticated: false,
  username: null,
});

// --- Getters ---
const getters = {
  // You can add computed properties here if needed
};

// --- Actions ---
const actions = {
  login(username) {
    state.isAuthenticated = true;
    state.username = username;
    // In a real app, you would also store the session token securely (e.g., in an HttpOnly cookie)
    localStorage.setItem('isAuthenticated', 'true');
    localStorage.setItem('username', username);
  },

  logout() {
    state.isAuthenticated = false;
    state.username = null;
    localStorage.removeItem('isAuthenticated');
    localStorage.removeItem('username');
    // Here you would also make an API call to invalidate the session on the server
  },

  // This action should be called when the app initializes
  checkAuth() {
    const isAuthenticated = localStorage.getItem('isAuthenticated') === 'true';
    const username = localStorage.getItem('username');
    if (isAuthenticated && username) {
      this.login(username);
    }
  },
};

// --- Store Export ---
// We are not using a formal library like Vuex or Pinia, so we export a simple store object.
export const store = {
  state: readonly(state),
  getters,
  actions,
};
