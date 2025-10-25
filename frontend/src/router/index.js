import { createRouter, createWebHistory } from 'vue-router';
import { useAuthStore } from '../stores/auth';
import EmailListView from '../views/EmailListView.vue';
import EmailDetailView from '../views/EmailDetailView.vue';
import LotteryView from '../views/LotteryView.vue';
import LotteryWinnersView from '../views/LotteryWinnersView.vue';

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes: [
    {
      path: '/',
      name: 'home',
      redirect: '/lottery', // Redirect home to the public lottery page
    },
    {
      path: '/lottery',
      name: 'lottery',
      component: LotteryView,
      // This route is now public
    },
    {
      path: '/lottery-winners',
      name: 'lottery-winners',
      component: LotteryWinnersView,
      meta: { requiresAuth: true }, // Protected route
    },
    {
      path: '/email-list',
      name: 'email-list',
      component: EmailListView,
      meta: { requiresAuth: true }, // Protected route
    },
    {
      path: '/email/:id',
      name: 'email-detail',
      component: EmailDetailView,
      props: true,
      meta: { requiresAuth: true }, // Protected route
    },
  ],
});

// --- Navigation Guard ---
router.beforeEach(async (to, from, next) => {
  const authStore = useAuthStore();

  // Ensure the initial auth check is complete before proceeding.
  if (!authStore.authCheckCompleted) {
    await authStore.checkAuth();
  }

  const requiresAuth = to.matched.some(record => record.meta.requiresAuth);

  if (requiresAuth && !authStore.isAuthenticated) {
    // For protected routes, we can prevent navigation if the user is not logged in.
    // Optionally, you could trigger the login modal here, but preventing navigation
    // is a simpler and more secure default.
    console.warn(`Navigation to "${to.path}" blocked. User is not authenticated.`);
    // You could redirect to a public page like '/lottery' or just stop the navigation
    if (from.name !== 'lottery') {
      next({ name: 'lottery' });
    } else {
      next(false); // Stop navigation
    }
  } else {
    next(); // Proceed as normal
  }
});

export default router;
