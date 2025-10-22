<template>
  <div class="auth-container">
    <h2>注册</h2>
    <form @submit.prevent="handleRegister">
      <div class="form-group">
        <label for="email">邮箱</label>
        <input type="email" id="email" v-model="email" required>
      </div>
      <div class="form-group">
        <label for="password">密码</label>
        <input type="password" id="password" v-model="password" required>
      </div>
      <button type="submit" :disabled="isLoading">注册</button>
      <p v-if="error" class="error-message">{{ error }}</p>
    </form>
    <p>已有账户？ <router-link to="/login">立即登录</router-link></p>
  </div>
</template>

<script setup>
import { ref } from 'vue';
import { useRouter } from 'vue-router';
import apiClient from '../api';

const email = ref('');
const password = ref('');
const error = ref(null);
const isLoading = ref(false);
const router = useRouter();

async function handleRegister() {
  isLoading.value = true;
  error.value = null;
  try {
    await apiClient.post('/register', {
      email: email.value,
      password: password.value,
    });
    router.push('/'); // Redirect to email list on successful registration
  } catch (err) {
    error.value = err.response?.data?.message || '注册失败，请稍后再试。';
  } finally {
    isLoading.value = false;
  }
}
</script>

<style scoped>
/* Add some basic styling for the auth form */
.auth-container {
  max-width: 400px;
  margin: 2rem auto;
  padding: 2rem;
  border: 1px solid #ddd;
  border-radius: 8px;
}
.form-group {
  margin-bottom: 1rem;
}
label {
  display: block;
  margin-bottom: 0.5rem;
}
input {
  width: 100%;
  padding: 0.5rem;
  border: 1px solid #ccc;
  border-radius: 4px;
}
button {
  width: 100%;
  padding: 0.75rem;
  border: none;
  background-color: #007bff;
  color: white;
  border-radius: 4px;
  cursor: pointer;
}
button:disabled {
  background-color: #ccc;
}
.error-message {
  color: #d9534f;
  margin-top: 1rem;
}
</style>
