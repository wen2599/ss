import { createRouter, createWebHistory } from 'vue-router';
import Lottery from '../views/Lottery.vue';
import Emails from '../views/Emails.vue';
import Login from '../views/Login.vue';
import Register from '../views/Register.vue';
import auth from '@/services/auth.js';

const routes = [
  {
    path: '/',
    name: 'Lottery',
    component: Lottery
  },
  {
    path: '/emails',
    name: 'Emails',
    component: Emails,
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
  }
];

const router = createRouter({
  history: createWebHistory(process.env.BASE_URL),
  routes
});

router.beforeEach((to, from, next) => {
  const loggedIn = auth.isLoggedIn();

  if (to.meta.requiresAuth) {
    if (loggedIn) {
      next();
    } else {
      next('/login');
    }
  } else if (to.meta.guestOnly) {
    if (loggedIn) {
      next('/emails');
    } else {
      next();
    }
  } else {
    next();
  }
});

export default router;
