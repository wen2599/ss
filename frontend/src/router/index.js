import { createRouter, createWebHistory } from 'vue-router';
import Home from '../views/Home.vue';
import Login from '../views/Login.vue'; // 导入登录视图
import Register from '../views/Register.vue'; // 导入注册视图

const routes = [
  {
    path: '/',
    name: 'Home',
    component: Home,
    meta: { requiresAuth: true } // 标记这个路由需要认证
  },
  {
    path: '/login',
    name: 'Login',
    component: Login,
    meta: { guestOnly: true } // 标记这个路由只对未登录用户开放
  },
  {
    path: '/register',
    name: 'Register',
    component: Register,
    meta: { guestOnly: true } // 标记这个路由只对未登录用户开放
  }
];

const router = createRouter({
  history: createWebHistory(process.env.BASE_URL),
  routes
});

// --- 路由守卫 --- 
// 这是实现受保护路由的核心
router.beforeEach((to, from, next) => {
  const loggedIn = localStorage.getItem('authToken'); // 检查本地存储中是否有token

  // 如果路由需要认证
  if (to.meta.requiresAuth) {
    if (loggedIn) {
      // 用户已登录，允许访问
      next();
    } else {
      // 用户未登录，重定向到登录页
      next('/login');
    }
  } 
  // 如果路由只对未登录用户开放
  else if (to.meta.guestOnly) {
    if (loggedIn) {
      // 用户已登录，重定向到主页
      next('/');
    } else {
      // 用户未登录，允许访问
      next();
    }
  } 
  // 对于其他所有情况（例如没有meta标记的路由）
  else {
    next();
  }
});

export default router;
