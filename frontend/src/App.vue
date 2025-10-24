<template>
  <!-- Main container with a light/dark background -->
  <div class="flex flex-col min-h-screen bg-gray-50 dark:bg-gray-900 text-gray-800 dark:text-gray-200">

    <!-- Header/Navigation Bar -->
    <header class="bg-white dark:bg-gray-800 shadow-md sticky top-0 z-50">
      <nav class="container mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex items-center justify-between h-16">
          <!-- Logo/Brand Name -->
          <div class="flex-shrink-0">
            <RouterLink to="/" class="text-2xl font-bold text-blue-600 dark:text-blue-500">
              🏆 开奖中心
            </RouterLink>
          </div>

          <!-- Desktop Navigation Links -->
          <div class="hidden md:flex items-center space-x-8">
            <template v-if="isAuthenticated">
              <RouterLink to="/lottery" class="px-3 py-2 rounded-md text-sm font-medium hover:bg-gray-100 dark:hover:bg-gray-700" active-class="font-semibold text-blue-600 dark:text-blue-500">开奖结果</RouterLink>
              <RouterLink to="/lottery-winners" class="px-3 py-2 rounded-md text-sm font-medium hover:bg-gray-100 dark:hover:bg-gray-700" active-class="font-semibold text-blue-600 dark:text-blue-500">中奖名单</RouterLink>
              <div class="flex items-center space-x-4">
                <span class="text-sm font-medium">欢迎, {{ username }}</span>
                <button @click="handleLogout" class="px-4 py-2 border border-transparent rounded-md text-sm font-medium text-white bg-red-600 hover:bg-red-700">
                  注销
                </button>
              </div>
            </template>
            <template v-else>
              <RouterLink to="/login" class="px-3 py-2 rounded-md text-sm font-medium hover:bg-gray-100 dark:hover:bg-gray-700" active-class="font-semibold text-blue-600 dark:text-blue-500">登录</RouterLink>
              <RouterLink to="/register" class="px-4 py-2 rounded-md text-sm font-medium text-white bg-blue-600 hover:bg-blue-700">
                注册
              </RouterLink>
            </template>
          </div>
          
          <!-- Simplified Mobile Menu -->
          <div class="md:hidden flex items-center">
             <template v-if="isAuthenticated">
                 <span class="text-sm mr-4">{{ username }}</span>
                 <button @click="handleLogout" class="px-3 py-1.5 border border-transparent rounded-md text-sm font-medium text-white bg-red-600 hover:bg-red-700">
                  注销
                </button>
             </template>
             <template v-else>
                <RouterLink to="/login" class="px-3 py-1.5 rounded-md text-sm font-medium hover:bg-gray-100 dark:hover:bg-gray-700">登录</RouterLink>
             </template>
          </div>

        </div>
      </nav>
    </header>

    <!-- Main Content Area -->
    <main class="flex-grow container mx-auto px-4 sm:px-6 lg:px-8 py-8">
      <RouterView />
    </main>

    <!-- Footer -->
    <footer class="bg-white dark:bg-gray-800 shadow-inner mt-auto">
      <div class="container mx-auto py-4 px-4 sm:px-6 lg:px-8 text-center text-sm text-gray-500 dark:text-gray-400">
        <p>&copy; {{ new Date().getFullYear() }} 开奖中心. All Rights Reserved.</p>
      </div>
    </footer>

  </div>
</template>

<script setup>
import { computed, onMounted } from 'vue';
import { RouterLink, RouterView, useRouter } from 'vue-router';
import { store } from './store';

const router = useRouter();

// Check authentication status when the component is mounted
onMounted(() => {
  store.actions.checkAuth();
});

// Computed properties to reactively access the state
const isAuthenticated = computed(() => store.state.isAuthenticated);
const username = computed(() => store.state.username);

// Logout action
function handleLogout() {
  store.actions.logout();
  // Redirect to the login page after logout
  router.push('/login');
}
</script>

<!-- No style block needed -->
