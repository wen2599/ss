// 文件名: api.js
// 路径: frontend/src/services/api.js
// 目的: 将 API 请求的基础 URL 修改为相对路径 /api，使其指向前端代理。

import axios from 'axios';

/**
 * 创建一个 Axios 实例。
 * 所有的 API 请求都将通过这个实例发出。
 * 
 * - baseURL: '/api'
 *   这意味着所有请求都会发送到当前域名的 /api 路径下。
 *   例如, 如果你的前端在 https://ss.wenxiuxiu.eu.org,
 *   一个 api.get('/lottery/get_latest.php') 的请求，
 *   最终会发送到 https://ss.wenxiuxiu.eu.org/api/lottery/get_latest.php。
 *   这个请求会被我们接下来创建的 _worker.js 文件拦截和处理。
 */
const api = axios.create({
  baseURL: '/api',
});

/**
 * 请求拦截器 (Request Interceptor)
 * 
 * 在每个请求被发送出去之前，这个函数会运行。
 * 它的作用是从 localStorage 中读取 JWT token，
 * 如果 token 存在，就把它添加到请求的 Authorization 头中。
 * 这样，所有需要认证的 API 请求都会自动带上凭证。
 */
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

/**
 * 响应拦截器 (Response Interceptor)
 * 
 * 在接收到后端响应后，这个函数会运行。
 * 它的主要作用是统一处理认证失败的情况。
 * 如果后端返回 401 Unauthorized 错误，说明 token 无效或已过期，
 * 它会自动清除本地存储的 token 和用户信息，并强制页面跳转到登录页。
 */
api.interceptors.response.use(
  (response) => {
    return response;
  },
  (error) => {
    if (error.response && error.response.status === 401) {
      localStorage.removeItem('token');
      localStorage.removeItem('user');
      // 使用 window.location.href 可以强制刷新页面，确保状态被完全重置
      window.location.href = '/login';
    }
    return Promise.reject(error);
  }
);

export default api;