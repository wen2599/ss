// src/services/api.js
import axios from 'axios';

// API的基础URL现在是相对路径，指向我们的代理
const API_BASE_URL = '/api'; 

const api = axios.create({
  baseURL: API_BASE_URL,
});

// Axios 请求拦截器保持不变，它继续负责添加JWT Token
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

export default api;