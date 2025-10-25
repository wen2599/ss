import axios from 'axios';

const apiClient = axios.create({
  baseURL: '/', // The base URL is the root, as requests are proxied.
  withCredentials: true,
});

export default apiClient;
