<template>
  <div id="app" class="flex flex-col min-h-screen bg-gray-100 dark:bg-gray-900">
    <header class="bg-white dark:bg-gray-800 shadow-md">
      <div class="container mx-auto px-4">
        <nav class="flex justify-between items-center py-4">
          <RouterLink to="/" class="text-2xl font-bold text-blue-600 dark:text-blue-400">
            🏆 Lottery Center
          </RouterLink>
          
          <!-- Mobile Menu Button -->
          <button @click="toggleMenu" class="md:hidden btn btn-secondary">
            Menu
          </button>

          <!-- Desktop Menu -->
          <div class="hidden md:flex items-center space-x-6">
            <div v-if="isAuthenticated" class="flex items-center space-x-4">
              <RouterLink to="/lottery" class="hover:text-blue-500">Lottery Results</RouterLink>
              <RouterLink to="/lottery-winners" class="hover:text-blue-500">Winners</RouterLink>
              <span class="font-medium">Welcome, {{ username }}</span>
              <button @click="handleLogout" class="btn btn-primary">Logout</button>
            </div>
            <div v-else class="flex items-center space-x-4">
              <RouterLink to="/login" class="hover:text-blue-500">Login</RouterLink>
              <RouterLink to="/register" class="btn btn-primary">Register</RouterLink>
            </div>
          </div>
        </nav>

        <!-- Mobile Menu -->
        <div v-if="isMenuOpen" class="md:hidden mt-2">
          <div v-if="isAuthenticated" class="flex flex-col space-y-2">
            <RouterLink to="/lottery" @click="closeMenu" class="block py-2 hover:text-blue-500">Lottery Results</RouterLink>
            <RouterLink to="/lottery-winners" @click="closeMenu" class="block py-2 hover:text-blue-500">Winners</RouterLink>
            <button @click="handleLogoutAndCloseMenu" class="btn btn-primary">Logout</button>
          </div>
          <div v-else class="flex flex-col space-y-2">
            <RouterLink to="/login" @click="closeMenu" class="block py-2 hover:text-blue-500">Login</RouterLink>
            <RouterLink to="/register" @click="closeMenu" class="btn btn-primary">Register</RouterLink>
          </div>
        </div>
      </div>
    </header>

    <main class="flex-grow container mx-auto px-4 py-8">
      <RouterView />
    </main>

    <footer class="bg-white dark:bg-gray-800 shadow-inner py-4">
      <div class="container mx-auto text-center text-gray-500 dark:text-gray-400">
        &copy; {{ new Date().getFullYear() }} Lottery Center. All Rights Reserved.
      </div>
    </footer>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { RouterLink, RouterView, useRouter } from 'vue-router';
import { store } from './store';

const router = useRouter();
const isMenuOpen = ref(false);

const isAuthenticated = computed(() => store.state.isAuthenticated);
const username = computed(() => store.state.username);

onMounted(() => {
  store.actions.checkAuth();
});

function toggleMenu() {
  isMenuOpen.value = !isMenuOpen.value;
}

function closeMenu() {
  isMenuOpen.value = false;
}

function handleLogout() {
  store.actions.logout();
  router.push('/login');
}

function handleLogoutAndCloseMenu() {
  handleLogout();
  closeMenu();
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
