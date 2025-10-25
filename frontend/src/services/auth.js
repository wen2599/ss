import axios from 'axios';

const API_URL = process.env.VUE_APP_API_BASE_URL || '/api';

const apiClient = axios.create({
  baseURL: API_URL,
  headers: {
    'Content-Type': 'application/json',
  },
});

/**
 * 发送注册请求
 * @param {string} email
 * @param {string} password
 * @returns {Promise<any>}
 */
const register = (email, password) => {
  return apiClient.post('/register.php', { email, password });
};

/**
 * 发送登录请求
 * @param {string} email
 * @param {string} password
 * @returns {Promise<any>}
 */
const login = async (email, password) => {
  const response = await apiClient.post('/login.php', { email, password });
  if (response.data && response.data.token) {
    // 登录成功，将token存入localStorage
    localStorage.setItem('authToken', response.data.token);
  }
  return response.data;
};

/**
 * 用户登出
 */
const logout = () => {
  // 从localStorage移除token
  localStorage.removeItem('authToken');
  // 为了确保应用状态完全重置，可以重新加载页面
  window.location.reload();
};

/**
 * 检查用户是否已登录
 * @returns {boolean}
 */
const isLoggedIn = () => {
  return !!localStorage.getItem('authToken');
};

/**
 * 获取认证token
 * @returns {string|null}
 */
const getToken = () => {
  return localStorage.getItem('authToken');
};

export default {
  register,
  login,
  logout,
  isLoggedIn,
  getToken,
};
