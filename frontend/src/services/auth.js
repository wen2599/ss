import api from './api';
import { useAuthStore } from '../stores/auth'; // 确保你的Pinia store路径正确

/**
 * [重构] 登录服务
 * @param {object} credentials - 包含 email 和 password 的对象
 * @returns {Promise<object>} 返回用户信息
 */
export const login = async (credentials) => {
  try {
    // 调用api.js封装的实例，它会自动处理URL和错误
    // 我们的后端登录成功后返回 { success: true, token: '...', user: {...} }
    const response = await api.post('/api.php', {
      action: 'login',
      ...credentials,
    });

    if (response.success && response.token && response.user) {
      // 获取Pinia store实例
      const authStore = useAuthStore();
      // 调用store中的action来更新状态
      authStore.setAuthentication(response.token, response.user);
      return response.user;
    } else {
      // 如果响应格式不符合预期，抛出错误
      throw new Error(response.message || '登录失败，返回数据格式不正确。');
    }
  } catch (error) {
    // 错误已由api.js的响应拦截器处理并提示用户
    // 这里只需再次抛出，让调用组件知道请求失败了
    console.error('登录服务出错:', error);
    throw error;
  }
};

/**
 * [重构] 注册服务
 * @param {object} userData - 包含 username, email, password 的对象
 * @returns {Promise<object>} 返回用户信息
 */
export const register = async (userData) => {
  try {
    const response = await api.post('/api.php', {
      action: 'register',
      ...userData,
    });

    if (response.success && response.token && response.user) {
      const authStore = useAuthStore();
      authStore.setAuthentication(response.token, response.user);
      return response.user;
    } else {
      throw new Error(response.message || '注册失败，返回数据格式不正确。');
    }
  } catch (error) {
    console.error('注册服务出错:', error);
    throw error;
  }
};

/**
 * [重构] 登出服务
 */
export const logout = () => {
  const authStore = useAuthStore();
  // 清除Pinia store中的状态，这将自动触发UI更新
  authStore.logout();
  // api.js的请求拦截器将不再发送token
  // 可以选择性地在这里调用后端logout接口，但这通常不是必须的，因为前端状态已清除
  // api.post('/api.php', { action: 'logout' }).catch(err => console.error("调用后端登出接口失败", err));
};

/**
 * [重构] 检查会话服务 (应用初始化时调用)
 * @returns {Promise<boolean>} 如果session有效返回true，否则返回false
 */
export const checkSession = async () => {
  try {
    // 假设后端 /check_session 能验证请求头中的Bearer Token
    const response = await api.post('/api.php', { action: 'check_session' });
    if (response.loggedIn && response.user) {
      // 如果后端确认token有效并返回了用户信息，可以更新本地用户信息
      const authStore = useAuthStore();
      if (authStore.token) { // 确保本地也有token，防止状态不一致
          authStore.setUser(response.user);
          return true;
      }
    }
    // 如果后端返回loggedIn为false，或本地没有token，则登出
    const authStore = useAuthStore();
    authStore.logout();
    return false;

  } catch (error) {
    // 如果请求失败（例如401），api.js的拦截器会自动处理登出逻辑
    // 所以这里只需返回false
    return false;
  }
};
