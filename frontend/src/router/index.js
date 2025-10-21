import { createRouter, createWebHistory } from 'vue-router'
import HomeView from '../views/HomeView.vue'

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes: [
    {
      path: '/',
      name: 'home',
      component: HomeView
    },
    {
      path: '/emails',
      name: 'emails',
      component: () => import('../views/EmailListView.vue')
    },
    {
      path: '/emails/:id',
      name: 'email-detail',
      component: () => import('../views/EmailDetailView.vue'),
      props: true
    }
  ]
})

export default router