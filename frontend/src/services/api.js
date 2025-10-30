// 文件名: api.js
// 路径: frontend/src/services/api.js
// 版本: Final - Full Physical Path

import axios from 'axios';

// --- 关键修改在这里 ---
// 我们将 baseURL 设置为包含 /public_html/ 的完整路径。
// 这样，前端发出的请求 URL 就会是：
// https://wenge.cloudns.ch/public_html/api/lottery/get_latest.php
// 这个 URL 直接对应了您服务器上文件的物理位置，
// 绕过了所有 URL 重写和服务器根目录配置的问题。
const api = axios.create({
  baseURL: 'https://wenge.cloudns.ch/public_html/api', 
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