<template>
  <div id="app">
    <header class="app-header">
      <div class="container">
        <div class="logo-container">
          <router-link to="/" class="logo">开奖号码</router-link>
        </div>
        <nav class="app-nav">
          <router-link to="/emails" class="nav-link">邮件</router-link>
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
    this.isLoggedIn = auth.isLoggedIn();
  },
  methods: {
    handleLogout() {
      auth.logout();
    },
  },
  watch: {
    '$route': function() {
      this.isLoggedIn = auth.isLoggedIn();
    }
  }
};
</script>

<style>
/* Styles from previous implementation */
body {
  margin: 0;
  font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
  background-color: #f8fafc;
  color: #1a202c;
}
/* ... etc ... */
</style>
