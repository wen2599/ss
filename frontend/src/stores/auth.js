import { defineStore } from 'pinia';
import { checkSession as apiCheckSession } from '../services/auth'; // 导入我们重构后的checkSession

export const useAuthStore = defineStore('auth', {
  state: () => ({
    token: localStorage.getItem('token') || null, // 从localStorage加载token
    user: JSON.parse(localStorage.getItem('user') || 'null'), // 从localStorage加载用户信息
  }),

  getters: {
    // 判断用户是否已登录的计算属性
    isLoggedIn: (state) => !!state.token,
    // 获取当前用户信息
    currentUser: (state) => state.user,
  },

  actions: {
    /**
     * 设置认证信息并保存到localStorage
     * @param {string} token - JWT token
     * @param {object} user - 用户对象
     */
    setAuthentication(token, user) {
      this.token = token;
      this.user = user;
      localStorage.setItem('token', token);
      localStorage.setItem('user', JSON.stringify(user));
    },

    /**
     * 更新用户数据（例如，用户头像或名称更改时）
     * @param {object} user - 更新后的用户对象
     */
    setUser(user) {
        this.user = user;
        localStorage.setItem('user', JSON.stringify(user));
    },

    /**
     * 执行登出操作，清除所有认证信息
     */
    logout() {
      this.token = null;
      this.user = null;
      localStorage.removeItem('token');
      localStorage.removeItem('user');
      // 登出后重定向到登录页
      window.location.href = '/login'; 
    },

    /**
     * 异步检查当前会话的有效性
     * 通常在应用初始化时调用
     * @returns {Promise<boolean>} - 会话是否有效
     */
    async checkSession() {
      if (!this.token) {
        this.logout(); // 如果本地没有token，直接登出
        return false;
      }
      try {
        // 调用authService中重构后的checkSession
        const isValid = await apiCheckSession(); 
        if (!isValid) {
          this.logout();
          return false;
        }
        return true;
      } catch (error) {
        console.error("会话检查失败:", error);
        this.logout();
        return false;
      }
    },
  },
});
