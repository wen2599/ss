import { createRouter, createWebHistory } from 'vue-router';
import Lottery from '../views/Lottery.vue';
import Emails from '../views/Emails.vue';

const routes = [
  {
    path: '/',
    name: 'Lottery',
    component: Lottery
  },
  {
    path: '/emails',
    name: 'Emails',
    component: Emails
  }
];

const router = createRouter({
  history: createWebHistory(process.env.BASE_URL),
  routes
});

export default router;
