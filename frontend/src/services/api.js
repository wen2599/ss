// 文件名: api.js
// 路径: frontend/src/services/api.js
// 版本: Revert to Direct Backend Call

import axios from 'axios';

// baseURL 改回您的真实后端 API 地址
const api = axios.create({
  baseURL: 'https://wenge.cloudns.ch', 
});

// ... (拦截器部分保持不变) ...
api.interceptors.request.use(
  (config) => {
    const token = localStorage.getItem('token');
    if (token) {
      config.headers['Authorization'] = `Bearer ${token}`;
    }
    return config;
  },
  (error) => {
    return Promise.reject(error);
  }
);
api.interceptors.response.use(
  (response) => {
    return response;
  },
  (error) => {
    if (error.response && error.response.status === 401) {
      localStorage.removeItem('token');
      localStorage.removeItem('user');
      window.location.href = '/login';
    }
    return Promise.reject(error);
  }
);
export default api;