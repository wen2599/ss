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

<script setup>
import { ref } from 'vue';
import { useAuthStore } from '@/stores/auth';
import { useRouter } from 'vue-router';

const username = ref('');
const email = ref('');
const password = ref('');
const confirmPassword = ref('');
const loading = ref(false);
const error = ref(null);
const successMessage = ref(null);

const authStore = useAuthStore();
const router = useRouter();

const handleRegister = async () => {
  loading.value = true;
  error.value = null;
  successMessage.value = null;

  if (password.value !== confirmPassword.value) {
    error.value = 'Passwords do not match.';
    loading.value = false;
    return;
  }

  if (password.value.length < 8) {
    error.value = 'Password must be at least 8 characters long.';
    loading.value = false;
    return;
  }

  try {
    await authStore.register({ 
      username: username.value, 
      email: email.value, 
      password: password.value 
    });
    successMessage.value = 'Account created successfully! Redirecting to login...';
    setTimeout(() => {
      router.push('/login');
    }, 2000);
  } catch (err) {
    error.value = err.message || 'An unknown error occurred during registration.';
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

.error-message, .success-message {
  margin-top: 1rem;
  margin-bottom: 1rem;
  font-size: 0.875rem;
  padding: 0.75rem;
  border-radius: 4px;
}

.error-message {
  color: var(--error-color);
  background-color: rgba(var(--error-color-rgb), 0.2);
}

.success-message {
    color: var(--success-color);
    background-color: rgba(var(--success-color-rgb), 0.2);
}

.switch-link {
  margin-top: 1.5rem;
}

.switch-link p {
  color: var(--text-secondary);
}
</style>
