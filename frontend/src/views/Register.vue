<template>
  <div class="auth-container">
    <div class="auth-card">
      <h2>创建账户</h2>
      <form @submit.prevent="handleRegister">
        <div class="form-group">
          <label for="email">邮箱地址</label>
          <input type="email" id="email" v-model="email" required autocomplete="email">
        </div>
        <div class="form-group">
          <label for="password">密码 (至少8位)</label>
          <input type="password" id="password" v-model="password" required autocomplete="new-password">
        </div>
        <div class="form-group">
          <label for="confirm-password">确认密码</label>
          <input type="password" id="confirm-password" v-model="confirmPassword" required autocomplete="new-password">
        </div>
        <div v-if="error" class="error-message">
          {{ error }}
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
import auth from '@/services/auth.js';

export default {
  name: 'RegisterView',
  data() {
    return {
      email: '',
      password: '',
      confirmPassword: '',
      loading: false,
      error: null,
    };
  },
    created() {
    if (auth.isLoggedIn()) {
      this.$router.push('/emails');
    }
  },
  methods: {
    async handleRegister() {
      this.loading = true;
      this.error = null;

      if (this.password !== this.confirmPassword) {
        this.error = '两次输入的密码不一致。';
        this.loading = false;
        return;
      }

      try {
        await auth.register({ email: this.email, password: this.password });
        this.$router.push('/emails').then(() => {
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
/* Styles from previous implementation */
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
}
/* ... etc ... */
</style>
