import { createRouter, createWebHistory } from 'vue-router'
import { routes } from './routes'

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes,
})

// ── Navigation guard ──────────────────────────────────────────────────────────
router.beforeEach((to) => {
  const publicRoutes = ['/login']
  const isPublic     = publicRoutes.includes(to.path)
  const token        = localStorage.getItem('qc_token')

  // Belum login → ke login
  if (!isPublic && !token) {
    return { path: '/login' }
  }

  // Sudah login + mau ke login → redirect ke home
  if (to.path === '/login' && token) {
    const role = _getStoredRole()
    return { path: _defaultRouteForRole(role) }
  }

  // Cek role — jika meta.roles tidak ada, izinkan semua yang sudah login
  if (to.meta?.roles && token) {
    const role = _getStoredRole()
    // Jika role belum ada di localStorage (belum fetchMe), izinkan dulu
    // fetchMe akan dipanggil di App.vue/restoreSession
    if (role && !to.meta.roles.includes(role)) {
      return { path: _defaultRouteForRole(role) }
    }
  }
})

function _getStoredRole() {
  try {
    const raw = localStorage.getItem('qc_user')
    if (!raw) return null
    return JSON.parse(raw)?.role ?? null
  } catch {
    return null
  }
}

function _defaultRouteForRole(role) {
  if (role === 'kasir') return '/batal-ranap-view'
  return '/dashboard'
}

export default function (app) {
  app.use(router)
}
export { router }
