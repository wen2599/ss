import axios from 'axios';
import { useAuthStore } from '../stores/auth'; // 假设你使用Pinia来管理状态

// 1. 配置API基础URL
// 使用Vite环境变量来区分开发和生产环境
// 在 .env.development 文件中设置 VITE_API_URL = http://localhost:8000 (或其他本地PHP服务器地址)
// 在 .env.production 文件中设置 VITE_API_URL = https://ss.wenxiuxiu.eu.org (你的线上API地址)
const api = axios.create({
  baseURL: import.meta.env.VITE_API_URL || '/',
  timeout: 10000, // 请求超时时间10秒
  headers: {
    'Content-Type': 'application/json',
    'X-Requested-With': 'XMLHttpRequest',
  },
});

// 2. 添加请求拦截器 (Request Interceptor)
api.interceptors.request.use(
  (config) => {
    const authStore = useAuthStore();
    const token = authStore.token;

    // 如果存在token，则在每个请求的Authorization头中附加token
    if (token) {
      config.headers.Authorization = `Bearer ${token}`;
    }
    return config;
  },
  (error) => {
    // 对请求错误做些什么
    console.error('请求拦截器错误:', error);
    return Promise.reject(error);
  }
);

// 3. 添加响应拦截器 (Response Interceptor)
api.interceptors.response.use(
  (response) => {
    // 2xx 范围内的状态码都会触发该函数。
    // 这里我们可以直接返回响应中的data部分，简化组件中的调用
    return response.data;
  },
  (error) => {
    // 超出 2xx 范围的状态码都会触发该函数。
    const authStore = useAuthStore();

    if (error.response) {
      // 请求成功发出且服务器也响应了状态码，但状态代码超出了 2xx 的范围
      const { status, data } = error.response;
      
      switch (status) {
        case 401:
          // Unauthorized: Token无效或过期
          // 触发Pinia store中的logout action
          authStore.logout();
          // 跳转到登录页面，这里可以通过触发事件或直接操作router
          window.location.href = '/login'; 
          alert('您的登录已过期，请重新登录。');
          break;
        case 403:
          // Forbidden: 权限不足
          alert('您没有权限执行此操作。');
          break;
        case 404:
          // Not Found: 请求的资源不存在
          alert('请求的资源未找到。');
          break;
        case 500:
        case 502:
        case 503:
          // Server Error: 服务器内部错误
          alert('服务器开小差了，请稍后再试。');
          break;
        default:
          // 其他HTTP错误
          alert(data.message || '发生了一个未知错误。');
          break;
      }
    } else if (error.request) {
      // 请求已经成功发起，但没有收到响应
      alert('无法连接到服务器，请检查您的网络连接。');
    } else {
      // 发送请求之前或者之后发生了错误
      alert('请求发送失败，请重试。');
    }

    return Promise.reject(error);
  }
);

// 导出封装后的axios实例
export default api;
