import { createRouter, createWebHistory } from 'vue-router'
import SchemaView from '@/views/SchemaView.vue'
import CardsView from '@/views/CardsView.vue'
import ListView from '@/views/ListView.vue'
import ReleasesView from '@/views/ReleasesView.vue'

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes: [
    {
      path: '/',
      redirect: '/schema',
    },
    {
      path: '/schema',
      name: 'schema',
      component: SchemaView,
    },
    {
      path: '/cards',
      name: 'cards',
      component: CardsView,
    },
    {
      path: '/list',
      name: 'list',
      component: ListView,
    },
    {
      path: '/releases',
      name: 'releases',
      component: ReleasesView,
    },
  ],
})

export default router
