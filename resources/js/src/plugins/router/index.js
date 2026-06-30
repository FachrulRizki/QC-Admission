import { createRouter, createWebHistory } from 'vue-router'
import { routes } from './routes'

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes,
})

// ── Navigation guard ──────────────────────────────────────────────────────────
router.beforeEach(async (to) => {
  const publicRoutes = ['/login']
  const isPublic     = publicRoutes.includes(to.path)
  const token        = localStorage.getItem('qc_token')

  if (!isPublic && !token) {
    return { path: '/login' }
  }

  if (to.path === '/login' && token) {
    return { path: '/dashboard' }
  }
})

export default function (app) {
  app.use(router)
}
export { router }
