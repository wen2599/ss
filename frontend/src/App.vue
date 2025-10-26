<template>
  <div id="app-container">
    <header class="app-header">
      <nav class="main-nav">
        <router-link to="/" class="nav-logo">SS</router-link>
        <div class="nav-links">
          <router-link to="/" class="nav-link">主页</router-link>
          <router-link to="/lottery" class="nav-link">开奖公告</router-link>
        </div>
        <div class="nav-actions">
          <button v-if="isLoggedIn" @click="logout" class="btn-logout">登出</button>
          <router-link v-else to="/login" class="btn-login">登录</router-link>
        </div>
      </nav>
    </header>
    <main class="app-main">
      <router-view />
    </main>
  </div>
</template>

<script>
import authService from './services/auth.js';
import { useRouter } from 'vue-router';
import { ref, onMounted, watch } from 'vue';

export default {
  name: 'App',
  setup() {
    const router = useRouter();
    const isLoggedIn = ref(authService.isLoggedIn());

    const logout = async () => {
      await authService.logout();
      isLoggedIn.value = false;
      router.push('/login');
    };

    onMounted(() => {
      // Set initial state
      isLoggedIn.value = authService.isLoggedIn();
    });

    // Watch for route changes to update login state, e.g., after login/register
    watch(() => router.currentRoute.value, () => {
      isLoggedIn.value = authService.isLoggedIn();
    });

    return { isLoggedIn, logout };
  }
};
</script>

<style scoped>
#app-container {
  display: flex;
  flex-direction: column;
  min-height: 100vh;
}

.app-header {
  background-color: var(--background-secondary);
  padding: 1rem 2rem;
  border-bottom: 1px solid var(--border-color);
  box-shadow: 0 2px 10px var(--shadow-color);
  position: sticky;
  top: 0;
  z-index: 1000;
}

.main-nav {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.nav-logo {
  font-size: 1.8rem;
  font-weight: 700;
  color: var(--primary-accent);
}

.nav-links {
  display: flex;
  gap: 2rem;
}

.nav-link {
  font-size: 1rem;
  font-weight: 600;
  color: var(--text-secondary);
  padding: 0.5rem 0;
  position: relative;
  transition: color 0.3s;
}

.nav-link::after {
  content: '';
  position: absolute;
  bottom: 0;
  left: 0;
  width: 0;
  height: 2px;
  background-color: var(--primary-accent);
  transition: width 0.3s;
}

.nav-link:hover,
.router-link-exact-active {
  color: var(--text-primary);
}

.nav-link:hover::after,
.router-link-exact-active::after {
  width: 100%;
}

.nav-actions .btn-login,
.nav-actions .btn-logout {
  padding: 0.5rem 1.2rem;
  border-radius: 6px;
  font-size: 0.9rem;
}

.btn-logout {
  background-color: var(--error-color);
}

.app-main {
  flex: 1;
  padding: 2rem;
}
</style>
