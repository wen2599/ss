<template>
  <div id="app-layout">
    <header class="header">
      <div class="logo">
        <h1>邮件查看器</h1>
      </div>
      <nav class="navigation">
        <template v-if="isAuthenticated">
          <RouterLink to="/">邮件列表</RouterLink>
          <RouterLink to="/lottery">彩票开奖</RouterLink>
          <span class="username">欢迎, {{ username }}</span>
          <button @click="handleLogout" class="logout-button">注销</button>
        </template>
        <template v-else>
          <RouterLink to="/login">登录</RouterLink>
          <RouterLink to="/register">注册</RouterLink>
        </template>
      </nav>
    </header>
    <main class="main-content">
      <RouterView />
    </main>
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

<style scoped>
#app-layout {
  display: flex;
  flex-direction: column;
  height: 100vh;
}

.header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 1rem 2rem;
  background-color: #333;
  color: white;
  border-bottom: 1px solid #444;
}

.logo h1 {
  margin: 0;
  font-size: 1.5rem;
}

.navigation {
  display: flex;
  align-items: center;
}

.navigation a {
  color: #fff;
  text-decoration: none;
  margin-left: 1rem;
  font-size: 1rem;
}

.navigation a:hover, .navigation a.router-link-exact-active {
  text-decoration: underline;
}

.username {
  margin-left: 1.5rem;
  font-weight: 500;
}

.logout-button {
  margin-left: 1rem;
  padding: 0.5rem 1rem;
  background-color: #d9534f;
  color: white;
  border: none;
  border-radius: 4px;
  cursor: pointer;
  font-size: 0.9rem;
}

.logout-button:hover {
  background-color: #c9302c;
}

.main-content {
  flex-grow: 1;
  padding: 2rem;
  background-color: #f4f4f4;
  overflow-y: auto;
}
</style>
