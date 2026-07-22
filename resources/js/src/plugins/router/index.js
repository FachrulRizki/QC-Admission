import { createRouter, createWebHistory } from 'vue-router'
import { usePage } from '@inertiajs/vue3'
import { routes } from './routes'

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes,
})

router.beforeEach((to) => {
  const auth  = usePage().props.auth
  const user  = auth?.user   ?? null
  const roles = auth?.roles  ?? []
  const perms = auth?.permissions ?? []

  const publicPaths = ['/login', '/akses-ditolak']
  const isPublic    = publicPaths.includes(to.path)

  // 1. Belum login → /login
  if (!user && !isPublic) {
    return { path: '/login' }
  }

  // 2. Sudah login → jangan masuk /login
  if (user && to.path === '/login') {
    // Redirect ke dashboard kalau punya akses, fallback ke view-data-input
    const target = perms.includes('dashboard:view') ? '/dashboard' : '/view-data-input'
    return { path: target }
  }

  // 3. Cek role/permission jika route mendefinisikan meta.roles
  const required = to.meta?.roles ?? []
  if (required.length > 0 && user) {
    const hasAccess = required.some(r => {
      // Format 'resource:scope' → cek permissions, selain itu cek roles
      if (r.includes(':')) return perms.includes(r)
      return roles.includes(r)
    })

    if (!hasAccess) {
      return { path: '/akses-ditolak' }
    }
  }

  // Lanjut
})

export default function (app) { app.use(router) }
export { router }
