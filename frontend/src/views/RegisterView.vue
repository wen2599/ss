<template>
  <div class="auth-container">
    <h2>创建新账户</h2>
    <form @submit.prevent="handleRegister">
      <div class="form-group">
        <label for="reg-email">邮箱</label>
        <input id="reg-email" v-model="email" type="email" placeholder="请输入您的邮箱" required autocomplete="email" />
      </div>
      <div class="form-group">
        <label for="reg-password">密码</label>
        <input id="reg-password" v-model="password" type="password" placeholder="请输入至少6位密码" required autocomplete="new-password" />
      </div>
      <div class="form-group">
        <label for="reg-confirm-password">确认密码</label>
        <input id="reg-confirm-password" v-model="confirmPassword" type="password" placeholder="请再次输入密码" required autocomplete="new-password" />
      </div>

      <div v-if="error" class="error-message">{{ error }}</div>

      <button type="submit" :disabled="isLoading">
        {{ isLoading ? '注册中...' : '注册' }}
      </button>
    </form>
    <p class="auth-switch">
      已有账户？ <router-link to="/login">立即登录</router-link>
    </p>
  </div>
</template>

<script setup>
import { ref } from 'vue';
import { useRouter } from 'vue-router';
import apiClient from '../api';

const email = ref('');
const password = ref('');
const confirmPassword = ref('');
const error = ref(null);
const isLoading = ref(false);
const router = useRouter();

async function handleRegister() {
  // --- Frontend Validation ---
  error.value = null;
  if (password.value.length < 6) {
    error.value = '密码长度不能少于6位。';
    return;
  }
  if (password.value !== confirmPassword.value) {
    error.value = '两次输入的密码不一致。';
    return;
  }

  isLoading.value = true;

  try {
    const response = await apiClient.post('/api/register', {
      email: email.value,
      password: password.value,
    });

    // On success, redirect to the main page (or a login success page)
    if (response.data.status === 'success') {
      router.push('/');
    } else {
      // This case might not be reached if the API uses proper status codes, but is good for robustness
      error.value = response.data.message || '注册失败，请稍后再试。';
    }

  } catch (err) {
    // Handle errors from the API (e.g., email already exists, validation failed)
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
  box-sizing: border-box; /* Important for consistent sizing */
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
