import axios from 'axios';

const api = axios.create({
  baseURL: 'https://wenge.cloudns.ch/api', // 你的后端 API 地址
  withCredentials: true, // 如果需要发送cookie (本例JWT不需要, 但保留是好习惯)
});

// 请求拦截器: 在每个请求的 header 中添加 Authorization Token
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

// 响应拦截器: 处理401 Unauthorized等错误
api.interceptors.response.use(
  (response) => {
    return response;
  },
  (error) => {
    if (error.response && error.response.status === 401) {
      // Token失效或未授权
      localStorage.removeItem('token');
      localStorage.removeItem('user');
      // 刷新页面以重定向到登录页
      window.location.href = '/login';
    }
    return Promise.reject(error);
  }
);

export default api;