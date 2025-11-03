// src/services/api.js
import axios from 'axios';

// baseURL 必须是相对路径 /api
// 这将确保所有 axios 请求都变成类似 https://ss.wenxiuxiu.eu.org/api/proxy.php...
// 这样的请求，从而能被 _worker.js 拦截
const API_BASE_URL = '/api'; 

const api = axios.create({
  baseURL: API_BASE_URL,
});

// Add an interceptor to include the auth token in all requests
api.interceptors.request.use(
  (config) => {
    const token = localStorage.getItem('authToken'); // Corrected key
    if (token) {
      config.headers['Authorization'] = `Bearer ${token}`;
    }
    return config;
  },
  (error) => {
    return Promise.reject(error);
  }
);

export default api;