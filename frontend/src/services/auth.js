import api from './api';

/**
 * [Refactored] Sends a login request to the API.
 * @param {object} credentials - User credentials (email, password).
 * @returns {Promise<object>} The API response.
 */
export const login = (credentials) => {
  return api.post('/api.php', {
    action: 'login',
    ...credentials,
  });
};

/**
 * [Refactored] Sends a registration request to the API.
 * @param {object} userData - User registration data.
 * @returns {Promise<object>} The API response.
 */
export const register = (userData) => {
  return api.post('/api.php', {
    action: 'register',
    ...userData,
  });
};

/**
 * [Refactored] Sends a request to check the current session validity.
 * @returns {Promise<object>} The API response.
 */
export const checkSession = () => {
  return api.post('/api.php', { action: 'check_session' });
};

/**
 * [New] The logout logic is now fully handled by the Pinia store,
 * as it is primarily a client-side state cleanup.
 * This function is being kept and adapted to ensure calls from other parts of the app
 * are correctly routed to the store.
 */
import { useAuthStore } from '../stores/auth';

export const logout = () => {
  const authStore = useAuthStore();
  authStore.logout();
};
