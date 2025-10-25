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

  // Ensure the user's authentication status is checked before navigating.
  if (!authStore.isAuthenticated) {
    await authStore.checkAuth();
  }

  // The decision to show/hide content is now handled by each view component
  // based on the authStore.isAuthenticated state. The guard's only job is
  // to ensure the auth state is resolved before the component loads.
  // This approach works well with modal-based authentication, as it avoids
  // redirecting the user away from their intended page.
  next();
});

export default router;
