<template>
  <div class="bg-gray-100 dark:bg-gray-900 min-h-screen flex flex-col items-center justify-center px-4">
    <div class="w-full max-w-md bg-white dark:bg-gray-800 rounded-lg shadow-md p-6 sm:p-8">
      <h2 class="text-2xl font-bold text-center text-gray-900 dark:text-white mb-6">
        登录您的账户
      </h2>

      <!-- Display registration success message -->
      <div v-if="successMessage" class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-lg relative mb-6 text-sm" role="alert">
        {{ successMessage }}
      </div>

      <form @submit.prevent="handleLogin" class="space-y-6">
        <div>
          <label for="login-email" class="block mb-2 text-sm font-medium text-gray-900 dark:text-gray-300">邮箱</label>
          <input 
            id="login-email" 
            v-model="email" 
            type="email" 
            placeholder="name@example.com" 
            required 
            autocomplete="email"
            class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white"
          />
        </div>

        <div>
          <label for="login-password" class="block mb-2 text-sm font-medium text-gray-900 dark:text-gray-300">密码</label>
          <input 
            id="login-password" 
            v-model="password" 
            type="password" 
            placeholder="••••••••" 
            required 
            autocomplete="current-password"
            class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white"
          />
        </div>

        <!-- Display login error message -->
        <div v-if="error" class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg relative text-sm" role="alert">
          {{ error }}
        </div>

        <button 
          type="submit" 
          :disabled="isLoading"
          class="w-full text-white bg-blue-600 hover:bg-blue-700 focus:ring-4 focus:outline-none focus:ring-blue-300 font-medium rounded-lg text-sm px-5 py-3 text-center dark:bg-blue-600 dark:hover:bg-blue-700 dark:focus:ring-blue-800 disabled:opacity-50 disabled:cursor-not-allowed"
        >
          {{ isLoading ? '登录中...' : '登 录' }}
        </button>

        <p class="text-sm font-medium text-gray-500 dark:text-gray-400 text-center">
          还没有账户？ 
          <router-link to="/register" class="text-blue-700 hover:underline dark:text-blue-500">立即注册</router-link>
        </p>
      </form>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { useRouter, useRoute } from 'vue-router';
import apiClient from '../api';
import { store } from '../store'; // Import the store

const email = ref('');
const password = ref('');
const error = ref(null);
const successMessage = ref('');
const isLoading = ref(false);
const router = useRouter();
const route = useRoute();

onMounted(() => {
  if (route.query.registered === 'true') {
    successMessage.value = '注册成功！现在您可以登录了。';
  }
});

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

<!-- No <style> block is needed as all styles are handled by Tailwind CSS utility classes -->
