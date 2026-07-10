// Vue Router — client-side navigation saja, auth diproteksi server-side oleh Laravel
export const routes = [
  { path: '/', redirect: '/dashboard' },

  { path: '/login',         component: () => import('@/pages/login.vue') },
  { path: '/akses-ditolak', component: () => import('@/pages/akses-ditolak.vue') },

  { path: '/dashboard',        component: () => import('@/pages/dashboard.vue'),                    meta: { roles: ['admin', 'qc_admission'] } },
  { path: '/quality-control',  component: () => import('@/pages/qc_admission/quality-control.vue'), meta: { roles: ['admin', 'qc_admission'] } },
  { path: '/edukasi-lanjutan', component: () => import('@/pages/qc_admission/edukasi-lanjutan.vue'),meta: { roles: ['admin', 'qc_admission'] } },
  { path: '/batal-ranap',      component: () => import('@/pages/qc_admission/batal-ranap.vue'),     meta: { roles: ['admin', 'qc_admission'] } },
  { path: '/batal-ranap-view', component: () => import('@/pages/qc_admission/batal-ranap-view.vue'),meta: { roles: ['admin', 'qc_admission', 'kasir'] } },
  { path: '/up-selling',       component: () => import('@/pages/qc_admission/up-selling.vue'),      meta: { roles: ['admin', 'qc_admission'] } },
  { path: '/view-data-input',  component: () => import('@/pages/qc_admission/view-data-input.vue'), meta: { roles: ['admin', 'qc_admission', 'kasir'] } },
  { path: '/activity-log',     component: () => import('@/pages/qc_admission/activity-log.vue'),    meta: { roles: ['admin'] } },
  { path: '/master-data',      component: () => import('@/pages/qc_admission/master-data.vue'),     meta: { roles: ['admin'] } },
  { path: '/user-management',  component: () => import('@/pages/qc_admission/user-management.vue'), meta: { roles: ['admin'] } },

  { path: '/:pathMatch(.*)*',  component: () => import('@/pages/error/404.vue') },
]
