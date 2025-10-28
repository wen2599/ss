<template>
  <div class="auth-container">
    <div class="auth-card card">
      <h2>创建账户</h2>
      <p>只需几步即可开始使用您的私人邮箱。</p>
      <form @submit.prevent="handleRegister">
        <div class="form-group">
          <label for="username">用户名</label>
          <input type="text" id="username" v-model="username" required autocomplete="username">
        </div>
        <div class="form-group">
          <label for="email">邮箱地址</label>
          <input type="email" id="email" v-model="email" required autocomplete="email">
        </div>
        <div class="form-group">
          <label for="password">密码 (最少8位)</label>
          <input type="password" id="password" v-model="password" required autocomplete="new-password">
        </div>
         <div class="form-group">
          <label for="confirm-password">确认密码</label>
          <input type="password" id="confirm-password" v-model="confirmPassword" required autocomplete="new-password">
        </div>
        <div v-if="error" class="error-message">
          {{ error }}
        </div>
        <div v-if="successMessage" class="success-message">
          {{ successMessage }}
        </div>
        <button type="submit" :disabled="loading" class="submit-btn">
          {{ loading ? '创建中...' : '创建账户' }}
        </button>
      </form>
      <div class="switch-link">
        <p>已有账户？ <router-link to="/login">直接登录</router-link></p>
      </div>
    </div>
  </div>
</template>

<script>
import auth from '@/services/auth.js'; // 导入认证服务

export default {
  name: 'RegisterView',
  data() {
    return {
      username: '',
      email: '',
      password: '',
      confirmPassword: '',
      loading: false,
      error: null,
      successMessage: null,
    };
  },
  methods: {
    async handleRegister() {
      this.loading = true;
      this.error = null;
      this.successMessage = null;

      if (this.password !== this.confirmPassword) {
        this.error = '两次输入的密码不一致。';
        this.loading = false;
        return;
      }

      if (this.password.length < 8) {
        this.error = '密码必须至少为8位。';
        this.loading = false;
        return;
      }

      try {
        await auth.register({ username: this.username, email: this.email, password: this.password });
        this.loading = false;
        this.successMessage = '账户创建成功！您现在可以登录了。';
        // 清空表单
        this.username = '';
        this.email = '';
        this.password = '';
        this.confirmPassword = '';
        // 几秒后跳转到登录页
        setTimeout(() => {
          this.$router.push('/login');
        }, 2000);

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
.auth-container {
  display: flex;
  justify-content: center;
  align-items: center;
  min-height: calc(100vh - 80px);
  /* background-color: var(--background-primary); */ /* 使用全局背景 */
}

.auth-card {
  width: 100%;
  max-width: 400px;
  /* padding: 2rem; */ /* 继承 card 样式 */
  /* background-color: var(--background-secondary); */ /* 继承 card 样式 */
  /* border-radius: 8px; */ /* 继承 card 样式 */
  /* box-shadow: 0 4px 6px var(--shadow-color); */ /* 继承 card 样式 */
  text-align: center;
}

.auth-card h2 {
  margin-bottom: 0.5rem;
  font-size: 1.75rem;
  font-weight: 600;
  color: var(--text-primary);
}

.auth-card p {
  margin-bottom: 1.5rem;
  color: var(--text-secondary);
}

.form-group {
  margin-bottom: 1rem;
  text-align: left;
}

label {
  display: block;
  margin-bottom: 0.5rem;
  font-weight: 500;
  color: var(--text-secondary);
}

/* input 样式已在 main.css 中全局定义 */

.submit-btn {
  width: 100%;
  /* padding: 0.75rem; */ /* 继承 button 样式 */
  /* border: none; */ /* 继承 button 样式 */
  /* border-radius: 4px; */ /* 继承 button 样式 */
  /* background-color: var(--primary-accent); */ /* 继承 button 样式 */
  /* color: white; */ /* 继承 button 样式 */
  /* font-size: 1rem; */ /* 继承 button 样式 */
  /* font-weight: 600; */ /* 继承 button 样式 */
  /* cursor: pointer; */ /* 继承 button 样式 */
  /* transition: background-color 0.2s; */ /* 继承 button 样式 */
}

.submit-btn:disabled {
  background-color: var(--background-tertiary);
  cursor: not-allowed;
}

.submit-btn:hover:not(:disabled) {
  background-color: var(--primary-accent-hover);
}

.error-message, .success-message {
  margin-top: 1rem;
  margin-bottom: 1rem;
  font-size: 0.875rem;
  padding: 0.75rem;
  border-radius: 4px;
}

.error-message {
  color: var(--error-color);
  background-color: rgba(var(--error-color-rgb), 0.2); /* 假设有 RGB 变量 */
}

.success-message {
    color: var(--success-color);
    background-color: rgba(var(--success-color-rgb), 0.2); /* 假设有 RGB 变量 */
}

.switch-link {
  margin-top: 1.5rem;
}

.switch-link p {
  color: var(--text-secondary);
}
</style>
