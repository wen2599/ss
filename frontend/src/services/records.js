import axios from 'axios';
import auth from './auth';

const API_URL = process.env.VUE_APP_API_BASE_URL || '/api';

const apiClient = axios.create({
  baseURL: API_URL,
});

// 使用请求拦截器来动态添加Authorization头
apiClient.interceptors.request.use(config => {
  const token = auth.getToken();
  if (token) {
    config.headers.Authorization = `Bearer ${token}`;
  }
  return config;
}, error => {
  return Promise.reject(error);
});

// 使用响应拦截器来处理全局的401错误
apiClient.interceptors.response.use(
  response => response, // 如果响应成功，直接返回
  error => {
    // 如果响应是401 (Unauthorized)，说明token可能已过期或无效
    if (error.response && error.response.status === 401) {
      console.error("认证失败或Token已过期，将自动登出。");
      auth.logout(); // 调用登出方法，清除token并刷新页面
    }
    return Promise.reject(error);
  }
);

/**
 * 从受保护的端点获取用户邮件记录
 * @returns {Promise<any>}
 */
const getMyRecords = () => {
  return apiClient.get('/get-my-records.php');
};

export default {
  getMyRecords,
};
