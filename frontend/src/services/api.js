//
// 文件名: api.js
// 路径: frontend/src/services/api.js
// 版本: Final
//

import axios from 'axios';

/**
 * 创建一个 Axios 实例，用于所有与后端 API 的通信。
 * 
 * - baseURL: 'https://wenge.cloudns.ch'
 *   这被设置为了您后端服务器的根 URL。
 *   之后所有的 API 请求都会基于这个地址发起。
 */
const api = axios.create({
  baseURL: 'https://wenge.cloudns.ch',
});


/**
 * 请求拦截器 (Request Interceptor)
 * 
 * 在每个请求发送出去之前，此函数会自动将存储在 localStorage 中的 JWT token
 * 添加到请求的 Authorization 头中。
 * 这确保了所有需要认证的请求都能携带正确的凭证。
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
    // 对请求错误做些什么
    return Promise.reject(error);
  }
);

/**
 * 响应拦截器 (Response Interceptor)
 * 
 * 在接收到后端响应后，此函数会检查响应状态。
 * 如果遇到 401 Unauthorized 错误（通常意味着 token 无效或过期），
 * 它会自动清除本地的用户认证信息，并强制页面重定向到登录页。
 */
api.interceptors.response.use(
  (response) => {
    // 对响应数据做点什么
    return response;
  },
  (error) => {
    if (error.response && error.response.status === 401) {
      localStorage.removeItem('token');
      localStorage.removeItem('user');
      window.location.href = '/login';
    }
    // 对响应错误做点什么
    return Promise.reject(error);
  }
);

export default api;