<template>
  <div id="app-container">
    <header class="app-header">
      <div class="logo">
        <RouterLink to="/">🏆 开奖中心</RouterLink>
      </div>
      <nav class="main-nav">
        <template v-if="isAuthenticated">
          <RouterLink to="/lottery">开奖结果</RouterLink>
          <RouterLink to="/lottery-winners">中奖名单</RouterLink>
          <div class="user-info">
            <span>{{ username }}</span>
            <button @click="handleLogout" class="button-logout">注销</button>
          </div>
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

    <footer class="app-footer">
      <p>&copy; {{ new Date().getFullYear() }} 开奖中心. All Rights Reserved.</p>
    </footer>
  </div>
</template>

<script setup>
import { computed, onMounted } from 'vue';
import { RouterLink, RouterView, useRouter } from 'vue-router'; // Added RouterView back
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
#app-container {
  display: flex;
  flex-direction: column;
  min-height: 100vh;
}

.app-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 1rem 2.5rem;
  background-color: var(--color-surface);
  border-bottom: 1px solid var(--color-border);
  box-shadow: 0 2px 8px rgba(0,0,0,0.3);
  position: sticky;
  top: 0;
  z-index: 100;
}

.logo a {
  font-size: 1.7rem;
  font-weight: 700;
  color: var(--color-primary);
  text-decoration: none;
}

.main-nav {
  display: flex;
  align-items: center;
  gap: 2rem;
}

.main-nav a {
  color: var(--color-text-secondary);
  text-decoration: none;
  font-size: 1rem;
  font-weight: 500;
  padding-bottom: 5px;
  border-bottom: 2px solid transparent;
  transition: color 0.3s ease, border-color 0.3s ease;
}

.main-nav a:hover {
  color: var(--color-text-primary);
}

/* Style for the active route link */
.main-nav a.router-link-exact-active {
  color: var(--color-primary);
  border-bottom-color: var(--color-primary);
}

.user-info {
  display: flex;
  align-items: center;
  gap: 1rem;
  margin-left: 1rem;
  color: var(--color-text-primary);
}

.button-logout {
  background: transparent;
  border: 1px solid var(--color-primary);
  color: var(--color-primary);
  padding: 0.4rem 0.8rem;
  border-radius: 6px;
  cursor: pointer;
  transition: background-color 0.3s ease, color 0.3s ease;
}

.button-logout:hover {
  background-color: var(--color-primary);
  color: var(--color-background);
}

.main-content {
  flex-grow: 1;
}

.app-footer {
  padding: 1.5rem 2.5rem;
  text-align: center;
  color: var(--color-text-secondary);
  background-color: var(--color-surface);
  border-top: 1px solid var(--color-border);
  font-size: 0.9rem;
  margin-top: auto; /* Pushes footer to the bottom */
}
</style>
