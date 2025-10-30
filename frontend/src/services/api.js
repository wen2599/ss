// 文件名: api.js
// 路径: frontend/src/services/api.js
// 版本: Final - New Endpoint Path

import axios from 'axios';

// --- 关键修改在这里 ---
// 我们不再使用 /api 目录，而是直接指向新的 /data 目录。
// baseURL 现在是 https://wenge.cloudns.ch/public_html/data
const api = axios.create({
  baseURL: 'https://wenge.cloudns.ch/public_html/data', 
});
// --- 修改结束 ---


// --- lottery/get_latest.php 的请求现在会变成 ---
// --- api.get('/get_latest.php') ---
// --- 这样请求的 URL 就是 https://wenge.cloudns.ch/public_html/data/get_latest.php ---


// 拦截器部分保持不变
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