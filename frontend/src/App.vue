<template>
  <div id="app">
    <header class="app-header">
      <div class="container">
        <div class="logo-container">
          <router-link to="/" class="logo">邮件仪表盘</router-link>
        </div>
        <nav class="app-nav">
          <template v-if="isLoggedIn">
            <button @click="handleLogout" class="nav-button">登出</button>
          </template>
          <template v-else>
            <router-link to="/login" class="nav-link">登录</router-link>
            <router-link to="/register" class="nav-link primary">注册</router-link>
          </template>
        </nav>
      </div>
    </header>
    <main>
      <router-view/>
    </main>
  </div>
</template>

<script>
import auth from '@/services/auth.js';

export default {
  name: 'App',
  data() {
    return {
      isLoggedIn: false,
    };
  },
  created() {
    // 组件创建时检查登录状态
    this.isLoggedIn = auth.isLoggedIn();
  },
  methods: {
    handleLogout() {
      auth.logout();
      // logout方法会自动刷新页面，这里不需要额外操作
    },
  },
  watch: {
    // 监听路由变化，以在导航后更新登录状态（例如，从登录页跳转后）
    '$route': function() {
      this.isLoggedIn = auth.isLoggedIn();
    }
  }
};
</script>

<style>
/* 全局样式 */
body {
  margin: 0;
  font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
  background-color: #f8fafc;
  color: #1a202c;
}

#app {
  -webkit-font-smoothing: antialiased;
  -moz-osx-font-smoothing: grayscale;
}

.container {
  width: 100%;
  max-width: 1200px;
  margin-left: auto;
  margin-right: auto;
  padding-left: 1rem;
  padding-right: 1rem;
}

/* 头部样式 */
.app-header {
  background-color: white;
  border-bottom: 1px solid #e2e8f0;
  padding: 1rem 0;
}

.app-header .container {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.logo {
  font-size: 1.5rem;
  font-weight: 700;
  color: #2d3748;
  text-decoration: none;
}

.app-nav {
  display: flex;
  align-items: center;
  gap: 1rem;
}

.nav-link, .nav-button {
  text-decoration: none;
  font-weight: 500;
  padding: 0.5rem 1rem;
  border-radius: 6px;
  transition: all 0.2s ease-in-out;
}

.nav-link {
  color: #4a5568;
}
.nav-link:hover {
  background-color: #edf2f7;
}

.nav-link.primary {
  background-color: #4f46e5;
  color: white;
}
.nav-link.primary:hover {
  background-color: #4338ca;
}

.nav-button {
  border: 1px solid #cbd5e0;
  background-color: transparent;
  color: #4a5568;
  cursor: pointer;
  font-size: 1rem;
}

.nav-button:hover {
  background-color: #edf2f7;
}

/* 主体内容区域 */
main {
  padding-top: 2rem;
  padding-bottom: 2rem;
}
</style>
