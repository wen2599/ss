import { createRouter, createWebHistory } from 'vue-router';
import EmailListView from '../views/EmailListView.vue';
import EmailDetailView from '../views/EmailDetailView.vue';
import LoginView from '../views/LoginView.vue';
import RegisterView from '../views/RegisterView.vue';
import LotteryView from '../views/LotteryView.vue';
import LotteryWinnersView from '../views/LotteryWinnersView.vue';
import { store } from '../store'; // Import the store

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes: [
    {
      path: '/login',
      name: 'login',
      component: LoginView,
      meta: { isPublic: true } // Mark as a public route
    },
    {
      path: '/register',
      name: 'register',
      component: RegisterView,
      meta: { isPublic: true } // Mark as a public route
    },
    {
      path: '/',
      name: 'email-list',
      component: EmailListView,
      meta: { requiresAuth: true } // Mark as a protected route
    },
    {
      path: '/email/:id',
      name: 'email-detail',
      component: EmailDetailView,
      props: true,
      meta: { requiresAuth: true } // Mark as a protected route
    },
    {
      path: '/lottery',
      name: 'lottery',
      component: LotteryView,
      meta: { requiresAuth: true } // Mark as a protected route
    },
    {
      path: '/lottery-winners',
      name: 'lottery-winners',
      component: LotteryWinnersView,
      meta: { requiresAuth: true } // Mark as a protected route
    }
  ]
});

// --- Navigation Guard ---
router.beforeEach((to, from, next) => {
  // Ensure auth state is checked before any navigation
  if (!store.state.isAuthenticated) {
    store.actions.checkAuth();
  }

  const isAuthenticated = store.state.isAuthenticated;

  if (to.meta.requiresAuth && !isAuthenticated) {
    // If the route requires authentication and the user is not logged in, redirect to the login page.
    next({ name: 'login' });
  } else if ((to.name === 'login' || to.name === 'register') && isAuthenticated) {
    // If the user is already logged in and tries to access login/register, redirect them to the home page.
    next({ name: 'email-list' });
  } else {
    // Otherwise, allow the navigation.
    next();
  }
});

export default router;
