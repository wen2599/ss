<template>
  <div v-if="isOpen" class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50" @click.self="$emit('close')">
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-xl p-8 max-w-sm w-full">
      <div class="flex justify-between items-center mb-6">
        <h2 class="text-2xl font-bold">{{ isLoginView ? '登录' : '注册' }}</h2>
        <button @click="$emit('close')" class="text-gray-500 hover:text-gray-700 dark:hover:text-gray-300" aria-label="Close">&times;</button>
      </div>

      <!-- Login Form -->
      <form v-if="isLoginView" @submit.prevent="handleLogin">
        <div class="mb-4">
          <label for="login-email" class="block text-sm font-medium mb-1">邮箱</label>
          <input type="email" id="login-email" v-model="loginForm.email" class="w-full input" required>
        </div>
        <div class="mb-6">
          <label for="login-password" class="block text-sm font-medium mb-1">密码</label>
          <input type="password" id="login-password" v-model="loginForm.password" class="w-full input" required>
        </div>
        <button type="submit" class="btn btn-primary w-full mb-4">登录</button>
        <p class="text-center text-sm">
          没有账户? <button @click.prevent="isLoginView = false" class="text-blue-500 hover:underline">创建一个</button>
        </p>
      </form>

      <!-- Registration Form -->
      <form v-else @submit.prevent="handleRegister">
        <div class="mb-4">
          <label for="register-email" class="block text-sm font-medium mb-1">邮箱</label>
          <input type="email" id="register-email" v-model="registerForm.email" class="w-full input" required>
        </div>
        <div class="mb-6">
          <label for="register-password" class="block text-sm font-medium mb-1">密码</label>
          <input type="password" id="register-password" v-model="registerForm.password" class="w-full input" required>
        </div>
        <!-- Optional Telegram fields can be added here if needed -->
        <button type="submit" class="btn btn-primary w-full mb-4">注册</button>
        <p class="text-center text-sm">
          已有账户? <button @click.prevent="isLoginView = true" class="text-blue-500 hover:underline">在此登录</button>
        </p>
      </form>

      <div v-if="error" class="mt-4 text-red-500 text-sm">
        {{ error }}
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, watch } from 'vue';
import { useAuthStore } from '../stores/auth';

const props = defineProps({
  isOpen: Boolean,
  initialView: String // 'login' or 'register'
});

const emit = defineEmits(['close']);
const authStore = useAuthStore();

const isLoginView = ref(props.initialView === 'login');
const loginForm = ref({ email: '', password: '' });
const registerForm = ref({ email: '', password: '' }); // Username removed
const error = ref(null);

watch(() => props.initialView, (newView) => {
  isLoginView.value = newView === 'login';
  error.value = null; // Reset error on view change
  // Reset forms
  loginForm.value = { email: '', password: '' };
  registerForm.value = { email: '', password: '' };
});

async function handleLogin() {
  error.value = null;
  const result = await authStore.login(loginForm.value.email, loginForm.value.password);
  if (result.success) {
    emit('close');
  } else {
    error.value = result.message || '登录失败. 请重试.';
  }
}

async function handleRegister() {
  error.value = null;
  const registerResult = await authStore.register(registerForm.value.email, registerForm.value.password);
  
  if (registerResult.success) {
    // Auto-login after successful registration
    const loginResult = await authStore.login(registerForm.value.email, registerForm.value.password);
    if (loginResult.success) {
      emit('close');
    } else {
      // This might happen if registration succeeds but login fails, which is rare.
      // Guide user to log in manually.
      error.value = '注册成功! 请手动登录.';
      isLoginView.value = true; // Switch to login view
    }
  } else {
    error.value = registerResult.message || '注册失败. 请重试.';
  }
}
</script>

<style scoped>
/* Styles are minimal and rely on global styles from main.css */
</style>
