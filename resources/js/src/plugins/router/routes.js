export const routes = [
  { path: '/', redirect: '/dashboard' },

  // ── Authenticated layout (vertical nav) ───────────────────────────────────
  {
    path: '/',
    component: () => import('@/layouts/default.vue'),
    children: [
      {
        path: 'dashboard',
        component: () => import('@/pages/dashboard.vue'),
      },

      // Quality Control
      {
        path: 'quality-control',
        component: () => import('@/pages/qc_admission/quality-control.vue'),
      },

      // Edukasi Lanjutan
      {
        path: 'edukasi-lanjutan',
        component: () => import('@/pages/qc_admission/edukasi-lanjutan.vue'),
      },

      // Batal Ranap (with internal sub-navigation)
      {
        path: 'batal-ranap',
        component: () => import('@/pages/qc_admission/batal-ranap.vue'),
      },

      // View Data Input
      {
        path: 'view-data-input',
        component: () => import('@/pages/qc_admission/view-data-input.vue'),
      },

      // Up Selling
      {
        path: 'up-selling',
        component: () => import('@/pages/qc_admission/up-selling.vue'),
      },
    ],
  },

  // ── Blank layout (auth pages) ─────────────────────────────────────────────
  {
    path: '/',
    component: () => import('@/layouts/blank.vue'),
    children: [
      {
        path: 'login',
        component: () => import('@/pages/login.vue'),
      },
      {
        path: '/:pathMatch(.*)*',
        component: () => import('@/pages/[...error].vue'),
      },
    ],
  },
]
