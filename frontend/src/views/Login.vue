<template>
  <div class="auth-container">
    <div class="auth-card card">
      <h2>登录</h2>
      <p>欢迎回来！请输入您的凭据以继续。</p>
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
        <p>还没有账户？ <router-link to="/register">创建一个</router-link></p>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref } from 'vue';
import { useAuthStore } from '@/stores/auth';
import { useRouter } from 'vue-router';

const email = ref('');
const password = ref('');
const loading = ref(false);
const error = ref(null);

const authStore = useAuthStore();
const router = useRouter();

const handleLogin = async () => {
  loading.value = true;
  error.value = null;
  try {
    await authStore.login({ email: email.value, password: password.value });
    // On successful login, the App.vue watcher will redirect to home, but we can be explicit
    router.push('/');
  } catch (err) {
    error.value = err.message || 'An unknown error occurred.';
  } finally {
    loading.value = false;
  }
};
</script>

<style scoped>
/* Styles remain the same */
.auth-container {
  display: flex;
  justify-content: center;
  align-items: center;
  min-height: calc(100vh - 80px);
}

.auth-card {
  width: 100%;
  max-width: 400px;
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

.submit-btn:disabled {
  background-color: var(--background-tertiary);
  cursor: not-allowed;
}

.submit-btn:hover:not(:disabled) {
  background-color: var(--primary-accent-hover);
}

.error-message {
  margin-top: 1rem;
  margin-bottom: 1rem;
  font-size: 0.875rem;
  padding: 0.75rem;
  border-radius: 4px;
  color: var(--error-color);
  background-color: rgba(var(--error-color-rgb), 0.2);
}

.switch-link {
  margin-top: 1.5rem;
}

.switch-link p {
  color: var(--text-secondary);
}
</style>
