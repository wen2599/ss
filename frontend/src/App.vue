<template>
  <div id="app" class="flex flex-col min-h-screen bg-gray-100 dark:bg-gray-900">
    <header class="bg-white dark:bg-gray-800 shadow-md">
      <div class="container mx-auto px-4">
        <nav class="flex justify-between items-center py-4">
          <RouterLink to="/" class="text-2xl font-bold text-blue-600 dark:text-blue-400">
            🏆 开奖中心
          </RouterLink>
          
          <!-- Mobile Menu Button -->
          <button 
            @click="toggleMenu"
            class="md:hidden btn btn-secondary"
            :aria-expanded="isMenuOpen"
            aria-controls="mobile-menu"
          >
            菜单
          </button>

          <!-- Desktop Menu -->
          <div class="hidden md:flex items-center space-x-6">
            <div v-if="isAuthenticated" class="flex items-center space-x-4">
              <RouterLink to="/lottery" class="hover:text-blue-500">开奖结果</RouterLink>
              <RouterLink to="/lottery-winners" class="hover:text-blue-500">中奖名单</RouterLink>
              <span class="font-medium">欢迎, {{ username }}</span>
              <button @click="handleLogout" class="btn btn-primary">登出</button>
            </div>
            <div v-else class="flex flex-center space-x-4">
              <button @click="openAuthModal('login')" class="hover:text-blue-500">登录</button>
              <button @click="openAuthModal('register')" class="btn btn-primary">注册</button>
            </div>
          </div>
        </nav>

        <!-- Mobile Menu -->
        <div v-if="isMenuOpen" id="mobile-menu" class="md:hidden mt-2">
          <div v-if="isAuthenticated" class="flex flex-col space-y-2">
            <RouterLink to="/lottery" @click="closeMenu" class="block py-2 hover:text-blue-500">开奖结果</RouterLink>
            <RouterLink to="/lottery-winners" @click="closeMenu" class="block py-2 hover:text-blue-500">中奖名单</RouterLink>
            <button @click="handleLogoutAndCloseMenu" class="btn btn-primary">登出</button>
          </div>
          <div v-else class="flex flex-col space-y-2">
            <button @click="openAuthModal('login'); closeMenu();" class="block py-2 hover:text-blue-500">登录</button>
            <button @click="openAuthModal('register'); closeMenu();" class="btn btn-primary">注册</button>
          </div>
        </div>
      </div>
    </header>

    <main class="flex-grow container mx-auto px-4 py-8">
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

const isMenuOpen = ref(false);
const isAuthModalOpen = ref(false);
const authModalView = ref('login'); // or 'register'

// Use Pinia store state and getters
const isAuthenticated = computed(() => authStore.isAuthenticated);
const username = computed(() => authStore.username);

onMounted(() => {
  authStore.checkAuth(); // Call Pinia action
});

function toggleMenu() {
  isMenuOpen.value = !isMenuOpen.value;
}

function closeMenu() {
  isMenuOpen.value = false;
}

function handleLogout() {
  authStore.logout(); // Call Pinia action
}

function handleLogoutAndCloseMenu() {
  handleLogout();
  closeMenu();
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
