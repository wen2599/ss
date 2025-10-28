import axios from 'axios';
import fetchAdapter from '@haverstack/axios-fetch-adapter';

const api = axios.create({
  baseURL: '/api',
  adapter: fetchAdapter,
  // The duplex option needs to be passed in the config of each request,
  // so we will use an interceptor to add it dynamically.
});

api.interceptors.request.use((config) => {
  if (config.method === 'post' || config.method === 'put' || config.method === 'patch') {
    config.duplex = 'half';
  }
  return config;
});

export default api;
