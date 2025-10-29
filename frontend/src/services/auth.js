import api from './api';

const authService = {
  login: async (email, password) => { // 接收 email 和 password
    try {
      const response = await api.post('/api.php', { action: 'login', email, password }); // 明确指定 action 和 API 路径
      // 后端通过 session 管理登录状态，前端不需要存储 token 或 user 到 localStorage
      // 登录成功后，后端会设置 session cookie，浏览器会自动管理
      return response;
    } catch (error) {
      console.error('Login failed:', error);
      throw error;
    }
  },

  register: async (username, email, password) => {
    try {
      const response = await api.post('/api.php', { action: 'register', username, email, password }); // 明确指定 action 和 API 路径
      return response;
    } catch (error) {
      console.error('Registration failed:', error);
      throw error;
    }
  },

  logout: async () => {
    try {
      const response = await api.post('/api.php', { action: 'logout' }); // 明确指定 action 和 API 路径
      // 后端会销毁 session，前端不需要清除 localStorage
      return response;
    } catch (error) {
      console.error('Logout failed:', error);
      throw error;
    }
  },

  checkSession: async () => {
    try {
      const response = await api.post('/api.php', { action: 'check_session' }); // 调用后端 check_session
      return response.loggedIn; // 返回登录状态
    } catch (error) {
      console.error('Check session failed:', error);
      return false; // 检查失败也认为是未登录
    }
  },

  // 这个方法在会话认证下可能不太需要，因为用户数据通常通过 checkSession 获取
  // 或者在登录成功响应中获取并临时使用
  getCurrentUser: async () => {
    try {
      const response = await api.post('/api.php', { action: 'check_session' });
      return response.user || null;
    } catch (error) {
      console.error('Get current user failed:', error);
      return null;
    }
  },

  // isLoggedIn 现在会异步调用 checkSession
  isLoggedIn: async () => {
    return await authService.checkSession();
  }
};

export default authService;
