/**
 * Route definitions — meta.roles dipakai oleh router guard (index.js).
 *
 * Nilai roles harus sesuai dengan nama role di Keycloak realm/client.
 * Tidak ada role string yang di-hardcode lebih dari ini — guard membaca
 * nilai aktual dari Keycloak via Inertia shared props.
 *
 * Untuk permission granular, gunakan format 'resource:scope':
 *   meta: { roles: ['quality-control:view'] }
 */
export const routes = [
  { path: '/', redirect: '/dashboard' },

  // Halaman publik
  { path: '/login',         component: () => import('@/pages/login.vue') },
  { path: '/akses-ditolak', component: () => import('@/pages/akses-ditolak.vue') },

  // Dashboard — admin + qc_admission
  {
    path: '/dashboard',
    component: () => import('@/pages/dashboard.vue'),
    meta: { roles: ['admin', 'qc_admission'] },
  },

  // QC & Edukasi — admin + qc_admission
  {
    path: '/quality-control',
    component: () => import('@/pages/qc_admission/quality-control.vue'),
    meta: { roles: ['admin', 'qc_admission'] },
  },
  {
    path: '/edukasi-lanjutan',
    component: () => import('@/pages/qc_admission/edukasi-lanjutan.vue'),
    meta: { roles: ['admin', 'qc_admission'] },
  },

  // Batal Ranap — admin + qc_admission (write), kasir lihat di batal-ranap-view
  {
    path: '/batal-ranap',
    component: () => import('@/pages/qc_admission/batal-ranap.vue'),
    meta: { roles: ['admin', 'qc_admission'] },
  },
  {
    path: '/batal-ranap-view',
    component: () => import('@/pages/qc_admission/batal-ranap-view.vue'),
    meta: { roles: ['admin', 'qc_admission', 'kasir'] },
  },

  // Up Selling — admin + qc_admission
  {
    path: '/up-selling',
    component: () => import('@/pages/qc_admission/up-selling.vue'),
    meta: { roles: ['admin', 'qc_admission'] },
  },

  // View Data Input — semua role
  {
    path: '/view-data-input',
    component: () => import('@/pages/qc_admission/view-data-input.vue'),
    meta: { roles: ['admin', 'qc_admission', 'kasir'] },
  },

  // Admin only
  {
    path: '/activity-log',
    component: () => import('@/pages/qc_admission/activity-log.vue'),
    meta: { roles: ['admin'] },
  },
  {
    path: '/master-data',
    component: () => import('@/pages/qc_admission/master-data.vue'),
    meta: { roles: ['admin'] },
  },
  {
    path: '/token-info',
    component: () => import('@/pages/qc_admission/token-info.vue'),
    meta: { roles: ['admin'] },
  },

  // 404
  { path: '/:pathMatch(.*)*', component: () => import('@/pages/error/404.vue') },
]
