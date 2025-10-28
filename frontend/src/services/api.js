import axios from 'axios';

const api = axios.create({
  baseURL: '/api',
});

api.interceptors.request.use((config) => {
  if (config.method === 'post' || config.method === 'put' || config.method === 'patch') {
    config.adapter = async (config) => {
      const response = await fetch(config.baseURL + config.url, {
        method: config.method,
        headers: config.headers,
        body: config.data,
        duplex: 'half',
      });
      return {
        data: await response.json(),
        status: response.status,
        statusText: response.statusText,
        headers: response.headers,
        config: config,
        request: null,
      };
    };
  }
  return config;
});

export default api;
