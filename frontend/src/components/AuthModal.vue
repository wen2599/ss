<template>
  <div v-if="isOpen" class="fixed inset-0 z-50 flex items-center justify-center bg-gray-900 bg-opacity-75" @click.self="$emit('close')">
    <div class="bg-white rounded-xl shadow-2xl p-8 max-w-md w-full m-4">
      <div class="flex justify-between items-start mb-6">
        <h2 class="text-2xl font-bold text-gray-800">{{ isLoginView ? '登录您的账户' : '创建新账户' }}</h2>
        <button @click="$emit('close')" class="text-gray-400 hover:text-gray-600 transition-colors" aria-label="Close">
          <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
          </svg>
        </button>
      </div>

      <!-- Shared Form Fields -->
      <form @submit.prevent="isLoginView ? handleLogin() : handleRegister()">
        <div class="space-y-4">
          <div>
            <label for="auth-email" class="block text-sm font-medium text-gray-600 mb-1">邮箱</label>
            <input type="email" id="auth-email" v-model="form.email" class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-primary focus:border-primary" required placeholder="you@example.com">
          </div>
          <div>
            <label for="auth-password" class="block text-sm font-medium text-gray-600 mb-1">密码</label>
            <input type="password" id="auth-password" v-model="form.password" class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-primary focus:border-primary" required placeholder="••••••••">
          </div>
        </div>

        <div v-if="error" class="mt-4 text-red-600 text-sm font-medium">
          {{ error }}
        </div>

        <button type="submit" class="px-4 py-2 rounded-md font-semibold text-sm transition-colors focus:outline-none focus:ring-2 focus:ring-offset-2 bg-primary text-white hover:bg-primary-hover focus:ring-primary w-full mt-6" :disabled="isLoading">
          <span v-if="isLoading">{{ isLoginView ? '正在登录...' : '正在注册...' }}</span>
          <span v-else>{{ isLoginView ? '登录' : '注册' }}</span>
        </button>

        <p class="text-center text-sm text-gray-500 mt-6">
          {{ isLoginView ? '没有账户?' : '已有账户?' }}
          <button @click.prevent="toggleView" class="font-semibold text-primary hover:underline">
            {{ isLoginView ? '创建一个' : '在此登录' }}
          </button>
        </p>
      </form>
    </div>
  </div>
</template>

<script setup>
import { ref, watch, computed } from 'vue';
import { useAuthStore } from '../stores/auth';

const props = defineProps({
  isOpen: Boolean,
  initialView: String // 'login' or 'register'
});

const emit = defineEmits(['close']);
const authStore = useAuthStore();

const isLoginView = ref(props.initialView === 'login');
const form = ref({ email: '', password: '' });
const error = ref(null);
const isLoading = computed(() => authStore.isLoading);

// Watch for prop changes to switch views
watch(() => props.initialView, (newView) => {
  isLoginView.value = newView === 'login';
  resetFormAndError();
});

// Function to switch between login and register views
function toggleView() {
  isLoginView.value = !isLoginView.value;
  resetFormAndError();
}

function resetFormAndError() {
  form.value = { email: '', password: '' };
  error.value = null;
}

async function handleLogin() {
  error.value = null;
  const result = await authStore.login(form.value.email, form.value.password);
  if (result.success) {
    emit('close');
  } else {
    error.value = result.message || '登录失败. 请重试.';
  }
}

async function handleRegister() {
  error.value = null;
  const registerResult = await authStore.register(form.value.email, form.value.password);
  
  if (registerResult.success) {
    // On successful registration, show a success message and switch to the login view
    // This provides clearer user feedback than auto-logging in.
    isLoginView.value = true;
    resetFormAndError();
    // A temporary success message could be implemented here if desired
    alert('注册成功! 请登录.'); // Using alert for simplicity, could be a custom notification
    // Or, attempt to auto-login
    // const loginResult = await authStore.login(form.value.email, form.value.password);
    // if (loginResult.success) emit('close');
    // else error.value = '注册成功，但自动登录失败. 请手动登录.';
  } else {
    error.value = registerResult.message || '注册失败. 请重试.';
  }
}
</script>
