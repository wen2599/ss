// 文件名: api.js
// 路径: frontend/src/services/api.js
// 版本: Final Path Correction for Direct Backend Call

import axios from 'axios';

// --- 关键修改在这里 ---
// baseURL 必须包含 /api/ 这一部分，
// 这样 axios 发出请求时才会正确拼接 URL。
// 旧的可能是: 'https://wenge.cloudns.ch'
// 正确的应该是: 'https://wenge.cloudns.ch/api'
const api = axios.create({
  baseURL: 'https://wenge.cloudns.ch/api', 
});
// --- 修改结束 ---

// 请求拦截器，用于附加 JWT Token
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

// 响应拦截器，用于处理 401 未授权错误
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