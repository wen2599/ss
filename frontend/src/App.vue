<template>
  <div id="app-layout">
    <header class="header">
      <div class="logo">
        <h1>邮件查看器</h1>
      </div>
      <nav class="navigation">
        <RouterLink to="/">邮件列表</RouterLink>
      </nav>
    </header>
    <main class="main-content">
      <RouterView />
    </main>
  </div>
</template>

<script setup>
import { RouterLink, RouterView } from 'vue-router'
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

.navigation a {
  color: #fff;
  text-decoration: none;
  margin-left: 1rem;
  font-size: 1rem;
}

.navigation a:hover, .navigation a.router-link-exact-active {
  text-decoration: underline;
}

.main-content {
  flex-grow: 1;
  padding: 2rem;
  background-color: #f4f4f4;
  overflow-y: auto;
}
</style>
