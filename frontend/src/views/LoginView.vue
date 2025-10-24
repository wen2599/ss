<template>
  <div class="auth-container">
    <h2>登录</h2>
    <form @submit.prevent="handleLogin">
      <div class="form-group">
        <label for="login-email">邮箱</label>
        <input id="login-email" v-model="email" type="email" placeholder="请输入您的邮箱" required autocomplete="email" />
      </div>
      <div class="form-group">
        <label for="login-password">密码</label>
        <input id="login-password" v-model="password" type="password" placeholder="请输入您的密码" required autocomplete="current-password" />
      </div>

      <div v-if="error" class="error-message">{{ error }}</div>

      <button type="submit" :disabled="isLoading">
        {{ isLoading ? '登录中...' : '登录' }}
      </button>
    </form>
    <p class="auth-switch">
      还没有账户？ <router-link to="/register">立即注册</router-link>
    </p>
  </div>
</template>

<script setup>
import { ref } from 'vue';
import { useRouter } from 'vue-router';
import apiClient from '../api';
import { store } from '../store'; // Import the store

const email = ref('');
const password = ref('');
const error = ref(null);
const isLoading = ref(false);
const router = useRouter();

async function handleLogin() {
  error.value = null;
  isLoading.value = true;

  try {
    const response = await apiClient.post('/login', {
      email: email.value,
      password: password.value,
    });

    if (response.data.status === 'success') {
      // --- Update Store ---
      const username = response.data.data.username;
      store.actions.login(username);

      router.push('/'); // Redirect to a protected route
    } else {
      error.value = response.data.message || '登录失败，请稍后再试。';
    }

  } catch (err) {
    if (err.response && err.response.data && err.response.data.message) {
      error.value = err.response.data.message;
    } else {
      error.value = '发生未知错误，请检查网络连接或稍后再试。';
    }
  } finally {
    isLoading.value = false;
  }
}
</script>

<style scoped>
/* Styles are unchanged */
.auth-container {
  max-width: 400px;
  margin: 3rem auto;
  padding: 2rem;
  background-color: #ffffff;
  border-radius: 8px;
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
  border: 1px solid #e7e7e7;
}
h2 {
  text-align: center;
  color: #333;
  margin-bottom: 1.5rem;
}
.form-group {
  margin-bottom: 1.25rem;
}
label {
  display: block;
  margin-bottom: 0.5rem;
  color: #555;
  font-weight: 500;
}
input {
  width: 100%;
  padding: 0.75rem;
  border: 1px solid #ccc;
  border-radius: 4px;
  box-sizing: border-box;
  transition: border-color 0.2s;
}
input:focus {
  border-color: #007bff;
  outline: none;
}
button {
  width: 100%;
  padding: 0.85rem;
  border: none;
  background-color: #007bff;
  color: white;
  border-radius: 4px;
  cursor: pointer;
  font-size: 1rem;
  font-weight: 600;
  transition: background-color 0.2s;
}
button:hover {
  background-color: #0056b3;
}
button:disabled {
  background-color: #a0a0a0;
  cursor: not-allowed;
}
.error-message {
  color: #e74c3c;
  background-color: #fdd;
  padding: 0.75rem;
  border-radius: 4px;
  margin-bottom: 1rem;
  text-align: center;
}
.auth-switch {
  text-align: center;
  margin-top: 1.5rem;
  color: #555;
}
.auth-switch a {
  color: #007bff;
  text-decoration: none;
  font-weight: 500;
}
.auth-switch a:hover {
  text-decoration: underline;
}
</style>
