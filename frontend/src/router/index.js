import { createRouter, createWebHistory } from 'vue-router';
import { useAuthStore } from '../stores/auth'; // 导入Pinia的认证store

import Home from '../views/Home.vue';
import Login from '../views/Login.vue';
import Register from '../views/Register.vue';
import MailOriginal from '../views/MailOriginal.vue';
import MailOrganize from '../views/MailOrganize.vue';
import Lottery from '../views/Lottery.vue'; // 导入Lottery视图
import LotteryResult from '../views/LotteryResult.vue';

const routes = [
  {
    path: '/',
    name: 'Home',
    component: Home,
    meta: { requiresAuth: true, title: '首页' }
  },
  {
    path: '/login',
    name: 'Login',
    component: Login,
    meta: { guestOnly: true, title: '登录' }
  },
  {
    path: '/register',
    name: 'Register',
    component: Register,
    meta: { guestOnly: true, title: '注册' }
  },
  {
    path: '/mail-original',
    name: 'MailOriginal',
    component: MailOriginal,
    meta: { requiresAuth: true, title: '原始邮件' }
  },
  {
    path: '/mail-organize',
    name: 'MailOrganize',
    component: MailOrganize,
    meta: { requiresAuth: true, title: '整理邮件' }
  },
  {
    path: '/lottery',
    name: 'Lottery',
    component: Lottery,
    meta: { requiresAuth: true, title: '六合彩' } // 新增Lottery路由
  },
  {
    path: '/lottery-result',
    name: 'LotteryResult',
    component: LotteryResult,
    meta: { requiresAuth: true, title: '开奖结果' }
  }
];

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL), // 使用import.meta.env.BASE_URL
  routes
});

// 路由全局前置守卫
router.beforeEach(async (to, from, next) => {
  const authStore = useAuthStore();

  // 尝试在每次路由跳转前检查一次会话，确保store中的登录状态是最新的
  // 注意: 过于频繁的后端会话检查可能会增加服务器负担，可以根据需求调整逻辑
  // 例如，只在页面刷新时检查，或结合JWT的过期时间判断。
  if (!authStore.isLoggedIn && authStore.token) {
     // 如果store里没有登录状态但有token（可能页面刷新导致状态丢失），尝试重新验证
     await authStore.checkSession();
  }

  const isLoggedIn = authStore.isLoggedIn;

  // 如果路由需要认证 (requiresAuth: true)
  if (to.meta.requiresAuth) {
    if (isLoggedIn) {
      next(); // 已登录，放行
    } else {
      // 未登录，重定向到登录页
      next({ name: 'Login', query: { redirect: to.fullPath } });
    }
  }
  // 如果路由只允许未登录用户访问 (guestOnly: true)
  else if (to.meta.guestOnly) {
    if (isLoggedIn) {
      next({ name: 'Home' }); // 已登录，重定向到首页
    } else {
      next(); // 未登录，放行
    }
  }
  // 其他公共路由，直接放行
  else {
    next();
  }

  // 更新页面标题
  if (to.meta.title) {
    document.title = `${to.meta.title} - 六合彩管理系统`;
  } else {
    document.title = '六合彩管理系统';
  }
});

export default router;
