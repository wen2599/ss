import { createRouter, createWebHistory } from 'vue-router'
import EmailListView from '../views/EmailListView.vue'
import EmailDetailView from '../views/EmailDetailView.vue'
import LoginView from '../views/LoginView.vue'
import RegisterView from '../views/RegisterView.vue'
import LotteryView from '../views/LotteryView.vue'

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes: [
    {
      path: '/login',
      name: 'login',
      component: LoginView
    },
    {
      path: '/register',
      name: 'register',
      component: RegisterView
    },
    {
      path: '/',
      name: 'email-list',
      component: EmailListView
    },
    {
      path: '/email/:id',
      name: 'email-detail',
      component: EmailDetailView,
      props: true // Pass route params as props to the component
    },
    {
      path: '/lottery',
      name: 'lottery',
      component: LotteryView
    }
  ]
})

export default router
