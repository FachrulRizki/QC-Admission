export const routes = [
  { path: '/', redirect: '/dashboard' },

  // ── Authenticated layout ───────────────────────────────────────────────────
  {
    path: '/',
    component: () => import('@/layouts/default.vue'),
    children: [
      {
        path: 'dashboard',
        component: () => import('@/pages/dashboard.vue'),
        meta: { roles: ['admin', 'qc_admission'] },
      },
      {
        path: 'quality-control',
        component: () => import('@/pages/qc_admission/quality-control.vue'),
        meta: { roles: ['admin', 'qc_admission'] },
      },
      {
        path: 'edukasi-lanjutan',
        component: () => import('@/pages/qc_admission/edukasi-lanjutan.vue'),
        meta: { roles: ['admin', 'qc_admission'] },
      },
      {
        path: 'batal-ranap',
        component: () => import('@/pages/qc_admission/batal-ranap.vue'),
        meta: { roles: ['admin', 'qc_admission'] },
      },
      {
        path: 'up-selling',
        component: () => import('@/pages/qc_admission/up-selling.vue'),
        meta: { roles: ['admin', 'qc_admission'] },
      },
      {
        path: 'view-data-input',
        component: () => import('@/pages/qc_admission/view-data-input.vue'),
        meta: { roles: ['admin', 'qc_admission'] },
      },
      {
        path: 'activity-log',
        component: () => import('@/pages/qc_admission/activity-log.vue'),
        meta: { roles: ['admin'] },
      },
      // Kasir — view only batal ranap
      {
        path: 'batal-ranap-view',
        component: () => import('@/pages/qc_admission/batal-ranap-view.vue'),
        meta: { roles: ['admin', 'qc_admission', 'kasir'] },
      },
    ],
  },

  // ── Blank layout ──────────────────────────────────────────────────────────
  {
    path: '/',
    component: () => import('@/layouts/blank.vue'),
    children: [
      { path: 'login', component: () => import('@/pages/login.vue') },
      { path: '/:pathMatch(.*)*', component: () => import('@/pages/[...error].vue') },
    ],
  },
]
