<template>
  <div id="app-layout" class="min-h-screen bg-gray-100 text-gray-800">
    <!-- Top Banner Header -->
    <header class="bg-white shadow-md sticky top-0 z-40">
      <div class="container mx-auto px-6 py-4">
        <div class="flex items-center justify-between">
          <div class="flex items-center">
            <RouterLink to="/" class="text-2xl font-bold text-gray-800">
              🏆 <span class="hidden sm:inline">开奖中心</span>
            </RouterLink>
          </div>

          <!-- Unified Navigation -->
          <nav class="flex items-center space-x-5">
            <RouterLink to="/lottery" class="text-gray-600 hover:text-primary font-medium transition-colors">開獎結果</RouterLink>
            <RouterLink v-if="isAuthenticated" to="/lottery-winners" class="text-gray-600 hover:text-primary font-medium transition-colors">中獎名单</RouterLink>

            <div v-if="authCheckCompleted">
              <div v-if="isAuthenticated" class="flex items-center space-x-4">
                <span class="text-sm font-medium">欢迎, {{ username }}</span>
                <button @click="handleLogout" class="px-4 py-2 rounded-md font-semibold text-sm transition-colors focus:outline-none focus:ring-2 focus:ring-offset-2 bg-gray-200 text-gray-800 hover:bg-gray-300 focus:ring-gray-400">登出</button>
              </div>
              <div v-else class="flex items-center space-x-2">
                <button @click="openAuthModal('login')" class="px-4 py-2 rounded-md font-semibold text-sm transition-colors focus:outline-none focus:ring-2 focus:ring-offset-2 bg-gray-200 text-gray-800 hover:bg-gray-300 focus:ring-gray-400">登录</button>
                <button @click="openAuthModal('register')" class="px-4 py-2 rounded-md font-semibold text-sm transition-colors focus:outline-none focus:ring-2 focus:ring-offset-2 bg-primary text-white hover:bg-primary-hover focus:ring-primary">注册</button>
              </div>
            </div>
          </nav>
        </div>
      </div>
    </header>

    <!-- Main Content -->
    <main class="flex-grow">
      <RouterView />
    </main>

    <footer class="bg-white dark:bg-gray-800 shadow-inner py-4">
      <div class="container mx-auto text-center text-gray-500 dark:text-gray-400">
        &copy; {{ new Date().getFullYear() }} 开奖中心. 版权所有.
      </div>
    </footer>
    
    <AuthModal :is-open="isAuthModalOpen" :initial-view="authModalView" @close="closeAuthModal" />
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { RouterLink, RouterView, useRouter } from 'vue-router';
// import { store } from './store'; // Removed direct import of old store
import { useAuthStore } from './stores/auth'; // Import new Pinia store
import AuthModal from './components/AuthModal.vue';

const router = useRouter();
const authStore = useAuthStore(); // Initialize Pinia store

const isAuthModalOpen = ref(false);
const authModalView = ref('login'); // or 'register'

// Use Pinia store state and getters
const isAuthenticated = computed(() => authStore.isAuthenticated);
const username = computed(() => authStore.username);
const authCheckCompleted = computed(() => authStore.authCheckCompleted);

onMounted(() => {
  authStore.checkAuth(); // Call Pinia action
});

function handleLogout() {
  authStore.logout(); // Call Pinia action
}

function openAuthModal(view) {
  authModalView.value = view;
  isAuthModalOpen.value = true;
}

function closeAuthModal() {
  isAuthModalOpen.value = false;
}
</script>

<style>
/* Minimal additional styling */
#app {
  display: flex;
  flex-direction: column;
  min-height: 100vh;
}

main {
  flex: 1;
}
</style>
