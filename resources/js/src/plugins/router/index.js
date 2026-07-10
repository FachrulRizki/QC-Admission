import { createRouter, createWebHistory } from 'vue-router'
import { routes } from './routes'

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes,
})

// Guard ringan — auth diproteksi server-side, ini hanya cegah user auth masuk /login
router.beforeEach((to) => {
  if (to.path === '/login') {
    const auth = window?.__inertia_page__?.props?.auth
    if (auth?.user) {
      return { path: auth.roles?.includes('kasir') ? '/view-data-input' : '/dashboard' }
    }
  }
})

export default function (app) { app.use(router) }
export { router }
