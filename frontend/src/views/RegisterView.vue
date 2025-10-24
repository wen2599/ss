<template>
  <div class="bg-gray-100 dark:bg-gray-900 min-h-screen flex flex-col items-center justify-center px-4">
    <div class="w-full max-w-md bg-white dark:bg-gray-800 rounded-lg shadow-md p-6 sm:p-8">
      <h2 class="text-2xl font-bold text-center text-gray-900 dark:text-white mb-6">
        创建新账户
      </h2>

      <form @submit.prevent="handleRegister" class="space-y-6">
        <div>
          <label for="reg-email" class="block mb-2 text-sm font-medium text-gray-900 dark:text-gray-300">邮箱</label>
          <input 
            id="reg-email" 
            v-model="email" 
            type="email" 
            placeholder="name@example.com" 
            required 
            autocomplete="email"
            class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white"
          />
        </div>

        <div>
          <label for="reg-password" class="block mb-2 text-sm font-medium text-gray-900 dark:text-gray-300">密码</label>
          <input 
            id="reg-password" 
            v-model="password" 
            type="password" 
            placeholder="请输入至少6位密码" 
            required 
            autocomplete="new-password"
            class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white"
          />
        </div>

        <div>
          <label for="reg-confirm-password" class="block mb-2 text-sm font-medium text-gray-900 dark:text-gray-300">确认密码</label>
          <input 
            id="reg-confirm-password" 
            v-model="confirmPassword" 
            type="password" 
            placeholder="请再次输入密码" 
            required 
            autocomplete="new-password"
            class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white"
          />
        </div>

        <!-- Display registration error message -->
        <div v-if="error" class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg relative text-sm" role="alert">
          {{ error }}
        </div>

        <button 
          type="submit" 
          :disabled="isLoading"
          class="w-full text-white bg-blue-600 hover:bg-blue-700 focus:ring-4 focus:outline-none focus:ring-blue-300 font-medium rounded-lg text-sm px-5 py-3 text-center dark:bg-blue-600 dark:hover:bg-blue-700 dark:focus:ring-blue-800 disabled:opacity-50 disabled:cursor-not-allowed"
        >
          {{ isLoading ? '注册中...' : '注 册' }}
        </button>

        <p class="text-sm font-medium text-gray-500 dark:text-gray-400 text-center">
          已有账户？ 
          <router-link to="/login" class="text-blue-700 hover:underline dark:text-blue-500">立即登录</router-link>
        </p>
      </form>
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
    const response = await apiClient.post('/register', {
      email: email.value,
      password: password.value,
    });

    // On success, redirect to the login page with a success message
    if (response.data.status === 'success') {
      router.push({ name: 'login', query: { registered: 'true' } });
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

<!-- No <style> block is needed as all styles are handled by Tailwind CSS utility classes -->
