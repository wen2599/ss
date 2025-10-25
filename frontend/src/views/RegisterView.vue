<template>
  <div class="flex items-center justify-center py-12 px-4 sm:px-6 lg:px-8">
    <div class="max-w-md w-full space-y-8">
      <div class="card">
        <h2 class="text-2xl font-bold text-center mb-6">创建新账户</h2>
        
        <form @submit.prevent="handleRegister" class="space-y-6">
          <div>
            <label for="email" class="block text-sm font-medium">邮箱</label>
            <input id="email" v-model="email" type="email" required autocomplete="email" class="form-input mt-1">
          </div>
          
          <div>
            <label for="password" class="block text-sm font-medium">密码</label>
            <input id="password" v-model="password" type="password" required autocomplete="new-password" class="form-input mt-1">
          </div>
          
          <div>
            <label for="confirm-password" class="block text-sm font-medium">确认密码</label>
            <input id="confirm-password" v-model="confirmPassword" type="password" required autocomplete="new-password" class="form-input mt-1">
          </div>
          
          <div v-if="error" class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg text-sm">
            {{ error }}
          </div>
          
          <button type="submit" :disabled="isLoading" class="w-full btn btn-primary">
            {{ isLoading ? '注册中...' : '注册' }}
          </button>
        </form>
        
        <p class="text-center mt-4">
          已有账户？ 
          <RouterLink to="/login" class="text-blue-600 hover:underline">登录</RouterLink>
        </p>
      </div>
    </div>
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
  error.value = null;
  if (password.value.length < 6) {
    error.value = '密码必须至少为 6 个字符。';
    return;
  }
  if (password.value !== confirmPassword.value) {
    error.value = '两次输入的密码不一致。';
    return;
  }

  isLoading.value = true;

  try {
    const response = await apiClient.post('/register', {
      email: email.value,
      password: password.value,
    });

    if (response.data.status === 'success') {
      router.push({ path: '/login', query: { registered: 'true' } });
    } else {
      error.value = response.data.message || '注册失败，请重试。';
    }
  } catch (err) {
    if (err.response && err.response.data && err.response.data.message) {
      error.value = err.response.data.message;
    } else {
      error.value = '发生未知错误，请重试。';
    }
  } finally {
    isLoading.value = false;
  }
}
</script>
