<template>
  <div class="auth-container">
    <div class="auth-card">
      <h2>登录</h2>
      <p>请输入您的邮箱和密码以访问您的邮件。</p>
      <form @submit.prevent="handleLogin">
        <div class="form-group">
          <label for="email">邮箱地址</label>
          <input type="email" id="email" v-model="email" required autocomplete="email">
        </div>
        <div class="form-group">
          <label for="password">密码</label>
          <input type="password" id="password" v-model="password" required autocomplete="current-password">
        </div>
        <div v-if="error" class="error-message">
          {{ error }}
        </div>
        <button type="submit" :disabled="loading" class="submit-btn">
          {{ loading ? '登录中...' : '登录' }}
        </button>
      </form>
      <div class="switch-link">
        <p>还没有账户？ <router-link to="/register">立即注册</router-link></p>
      </div>
    </div>
  </div>
</template>

<script>
import auth from '@/services/auth.js'; // 导入认证服务

export default {
  name: 'LoginView',
  data() {
    return {
      email: '',
      password: '',
      loading: false,
      error: null,
    };
  },
  methods: {
    async handleLogin() {
      this.loading = true;
      this.error = null;
      try {
        await auth.login(this.email, this.password);
        // 登录成功后，路由守卫会自动处理跳转，但我们也可以手动跳转
        // 为了更好的用户体验，直接跳转到根路径，然后让页面重新加载以应用状态
        this.$router.push('/').then(() => {
          window.location.reload();
        });
      } catch (err) {
        this.loading = false;
        if (err.response && err.response.data && err.response.data.message) {
          this.error = err.response.data.message;
        } else {
          this.error = '发生未知错误，请稍后再试。';
        }
      }
    },
  },
};
</script>

<style scoped>
/* 样式保持不变 */
.auth-container {
  display: flex;
  justify-content: center;
  align-items: center;
  min-height: calc(100vh - 80px);
  background-color: #f3f4f6;
}
.auth-card {
  width: 100%;
  max-width: 400px;
  padding: 2rem;
  background-color: white;
  border-radius: 8px;
  box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
  text-align: center;
}
h2 {
  margin-bottom: 0.5rem;
  font-size: 1.75rem;
  font-weight: 600;
}
p {
  margin-bottom: 1.5rem;
  color: #6b7280;
}
.form-group {
  margin-bottom: 1rem;
  text-align: left;
}
label {
  display: block;
  margin-bottom: 0.5rem;
  font-weight: 500;
  color: #374151;
}
input {
  width: 100%;
  padding: 0.75rem;
  border: 1px solid #d1d5db;
  border-radius: 4px;
  box-sizing: border-box;
}
.submit-btn {
  width: 100%;
  padding: 0.75rem;
  border: none;
  border-radius: 4px;
  background-color: #4f46e5;
  color: white;
  font-size: 1rem;
  font-weight: 600;
  cursor: pointer;
  transition: background-color 0.2s;
}
.submit-btn:disabled {
  background-color: #a5b4fc;
  cursor: not-allowed;
}
.submit-btn:hover:not(:disabled) {
  background-color: #4338ca;
}
.error-message {
  margin-top: 1rem;
  margin-bottom: 1rem;
  color: #ef4444;
  font-size: 0.875rem;
}
.switch-link {
  margin-top: 1.5rem;
}
</style>
