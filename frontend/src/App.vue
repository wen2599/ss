<template>
  <div id="app">
    <header class="app-header">
      <div class="container">
        <div class="logo-container">
          <router-link to="/" class="logo">开奖号码</router-link>
        </div>
        <nav class="app-nav">
          <router-link to="/emails" class="nav-link">邮件</router-link>
        </nav>
      </div>
    </header>
    <main>
      <router-view/>
    </main>
  </div>
</template>

<script>
export default {
  name: 'App',
};
</script>

<style>
/* Global styles remain the same */
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

.nav-link {
  text-decoration: none;
  font-weight: 500;
  padding: 0.5rem 1rem;
  border-radius: 6px;
  transition: all 0.2s ease-in-out;
  color: #4a5568;
}
.nav-link:hover {
  background-color: #edf2f7;
}

main {
  padding-top: 2rem;
  padding-bottom: 2rem;
}
</style>
