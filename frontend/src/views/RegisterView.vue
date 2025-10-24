<template>
  <div class="flex items-center justify-center py-12 px-4 sm:px-6 lg:px-8">
    <div class="max-w-md w-full space-y-8">
      <div class="card">
        <h2 class="text-2xl font-bold text-center mb-6">Create a New Account</h2>
        
        <form @submit.prevent="handleRegister" class="space-y-6">
          <div>
            <label for="email" class="block text-sm font-medium">Email</label>
            <input id="email" v-model="email" type="email" required autocomplete="email" class="form-input mt-1">
          </div>
          
          <div>
            <label for="password" class="block text-sm font-medium">Password</label>
            <input id="password" v-model="password" type="password" required autocomplete="new-password" class="form-input mt-1">
          </div>
          
          <div>
            <label for="confirm-password" class="block text-sm font-medium">Confirm Password</label>
            <input id="confirm-password" v-model="confirmPassword" type="password" required autocomplete="new-password" class="form-input mt-1">
          </div>
          
          <div v-if="error" class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg text-sm">
            {{ error }}
          </div>
          
          <button type="submit" :disabled="isLoading" class="w-full btn btn-primary">
            {{ isLoading ? 'Registering...' : 'Register' }}
          </button>
        </form>
        
        <p class="text-center mt-4">
          Already have an account? 
          <RouterLink to="/login" class="text-blue-600 hover:underline">Login</RouterLink>
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
    error.value = 'Password must be at least 6 characters long.';
    return;
  }
  if (password.value !== confirmPassword.value) {
    error.value = 'Passwords do not match.';
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
      error.value = response.data.message || 'Registration failed. Please try again.';
    }
  } catch (err) {
    if (err.response && err.response.data && err.response.data.message) {
      error.value = err.response.data.message;
    } else {
      error.value = 'An unknown error occurred. Please try again.';
    }
  } finally {
    isLoading.value = false;
  }
}
</script>
