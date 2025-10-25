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

          <!-- Desktop Navigation -->
          <nav class="hidden md:flex items-center space-x-5">
            <RouterLink to="/lottery" class="nav-link">开奖结果</RouterLink>
            <RouterLink v-if="isAuthenticated" to="/lottery-winners" class="nav-link">中奖名单</RouterLink>

            <div v-if="authCheckCompleted">
              <div v-if="isAuthenticated" class="flex items-center space-x-4">
                <span class="text-sm font-medium">欢迎, {{ username }}</span>
                <button @click="handleLogout" class="btn btn-secondary">登出</button>
              </div>
              <div v-else class="flex items-center space-x-2">
                <button @click="openAuthModal('login')" class="btn btn-secondary">登录</button>
                <button @click="openAuthModal('register')" class="btn btn-primary">注册</button>
              </div>
            </div>
          </nav>

          <!-- Mobile Menu Button -->
          <div class="md:hidden">
            <button @click="isMenuOpen = !isMenuOpen" class="text-gray-600 hover:text-gray-800 focus:outline-none">
              <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16m-7 6h7" />
              </svg>
            </button>
          </div>
        </div>

        <!-- Mobile Menu -->
        <div v-if="isMenuOpen" class="md:hidden mt-4">
          <nav class="flex flex-col space-y-2">
            <RouterLink to="/lottery" class="nav-link-mobile" @click="isMenuOpen = false">开奖结果</RouterLink>
            <RouterLink v-if="isAuthenticated" to="/lottery-winners" class="nav-link-mobile" @click="isMenuOpen = false">中奖名单</RouterLink>

            <div v-if="authCheckCompleted" class="border-t pt-4 mt-4">
              <div v-if="isAuthenticated" class="flex flex-col space-y-3">
                <span class="text-sm font-medium text-center">欢迎, {{ username }}</span>
                <button @click="handleLogoutAndCloseMenu" class="btn btn-secondary w-full">登出</button>
              </div>
              <div v-else class="flex flex-col space-y-3">
                <button @click="openAuthModal('login'); isMenuOpen = false;" class="btn btn-secondary w-full">登录</button>
                <button @click="openAuthModal('register'); isMenuOpen = false;" class="btn btn-primary w-full">注册</button>
              </div>
            </div>
          </nav>
        </div>
      </div>
    </header>

    <!-- Main Content -->
    <main class="flex-grow">
      <div class="container mx-auto px-6 py-8">
        <RouterView />
      </div>
    </main>

    <!-- Footer -->
    <footer class="bg-white border-t">
      <div class="container mx-auto px-6 py-4 text-center text-sm text-gray-500">
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
const authCheckCompleted = computed(() => authStore.authCheckCompleted);

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
