/**
 * Route definitions — meta.roles dipakai oleh router guard (index.js).
 *
 * Gunakan format permission 'resource:scope' agar tidak tergantung nama role.
 * Siapapun yang punya permission tersebut di Keycloak bisa akses halaman ini.
 */
export const routes = [
  { path: '/', redirect: '/dashboard' },

  // Halaman publik
  { path: '/login',         component: () => import('@/pages/login.vue') },
  { path: '/akses-ditolak', component: () => import('@/pages/akses-ditolak.vue') },

  // Dashboard
  {
    path: '/dashboard',
    component: () => import('@/pages/dashboard.vue'),
    meta: { roles: ['dashboard:view'] },
  },

  // QC & Edukasi
  {
    path: '/quality-control',
    component: () => import('@/pages/qc_admission/quality-control.vue'),
    meta: { roles: ['quality-control:view'] },
  },
  {
    path: '/edukasi-lanjutan',
    component: () => import('@/pages/qc_admission/edukasi-lanjutan.vue'),
    meta: { roles: ['edukasi-lanjutan:view'] },
  },

  // Batal Ranap
  {
    path: '/batal-ranap',
    component: () => import('@/pages/qc_admission/batal-ranap.vue'),
    meta: { roles: ['batal-ranap:view'] },
  },
  {
    path: '/batal-ranap-view',
    component: () => import('@/pages/qc_admission/batal-ranap-view.vue'),
    meta: { roles: ['batal-ranap:view'] },
  },

  // Up Selling
  {
    path: '/up-selling',
    component: () => import('@/pages/qc_admission/up-selling.vue'),
    meta: { roles: ['up-selling:view'] },
  },

  // View Data Input — semua user terautentikasi
  {
    path: '/view-data-input',
    component: () => import('@/pages/qc_admission/view-data-input.vue'),
    meta: { roles: [] }, // kosong = hanya butuh login
  },

  // Admin/privileged
  {
    path: '/activity-log',
    component: () => import('@/pages/qc_admission/activity-log.vue'),
    meta: { roles: ['activity-log:view'] },
  },
  {
    path: '/master-data',
    component: () => import('@/pages/qc_admission/master-data.vue'),
    meta: { roles: ['master-data:view'] },
  },
  {
    path: '/token-info',
    component: () => import('@/pages/qc_admission/token-info.vue'),
    meta: { roles: ['admin'] }, // tetap role — hanya admin yang lihat token
  },

  // 404
  { path: '/:pathMatch(.*)*', component: () => import('@/pages/error/404.vue') },
]
