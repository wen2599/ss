import { createRouter, createWebHistory } from 'vue-router';
import Home from '../views/Home.vue';
import Login from '../views/Login.vue';
import Register from '../views/Register.vue';
import MailOriginal from '../views/MailOriginal.vue';
import MailOrganize from '../views/MailOrganize.vue';
import LotteryResult from '../views/LotteryResult.vue';

const routes = [
  {
    path: '/',
    name: 'Home',
    component: Home,
    meta: { requiresAuth: true }
  },
  {
    path: '/login',
    name: 'Login',
    component: Login,
    meta: { guestOnly: true }
  },
  {
    path: '/register',
    name: 'Register',
    component: Register,
    meta: { guestOnly: true }
  },
  {
    path: '/mail-original',
    name: 'MailOriginal',
    component: MailOriginal,
    meta: { requiresAuth: true }
  },
  {
    path: '/mail-organize',
    name: 'MailOrganize',
    component: MailOrganize,
    meta: { requiresAuth: true }
  },
  {
    path: '/lottery-result',
    name: 'LotteryResult',
    component: LotteryResult,
    meta: { requiresAuth: true }
  }
];

const router = createRouter({
  history: createWebHistory(process.env.BASE_URL),
  routes
});

router.beforeEach((to, from, next) => {
  const loggedIn = localStorage.getItem('user_id'); // 检查localStorage中是否有user_id

  if (to.meta.requiresAuth) {
    if (loggedIn) {
      next();
    } else {
      next('/login');
    }
  }
  else if (to.meta.guestOnly) {
    if (loggedIn) {
      next('/');
    } else {
      next();
    }
  }
  else {
    next();
  }
});

export default router;
