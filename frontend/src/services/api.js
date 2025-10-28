import axios from 'axios';

const api = axios.create({
  baseURL: '/api.php', // Now directly pointing to the unified API entry
});

// Removed the custom adapter. Axios will now use its default adapter.

export default api;
