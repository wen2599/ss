import { createRouter, createWebHistory } from 'vue-router'
import EmailListView from '../views/EmailListView.vue'
import EmailDetailView from '../views/EmailDetailView.vue'

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes: [
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
    }
  ]
})

export default router
