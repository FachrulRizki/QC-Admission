export const routes = [
  { path: '/', redirect: '/dashboard' },

  {
    path: '/',
    component: () => import('@/layouts/default.vue'),
    children: [
      {
        path: 'dashboard',
        component: () => import('@/pages/dashboard.vue'),
      },

      {
        path: 'quality-control',
        component: () => import('@/pages/qc_admission/quality-control.vue'),
      },

      {
        path: 'edukasi-lanjutan',
        component: () => import('@/pages/qc_admission/edukasi-lanjutan.vue'),
      },

      {
        path: 'batal-ranap',
        component: () => import('@/pages/qc_admission/batal-ranap.vue'),
      },

      {
        path: 'up-selling',
        component: () => import('@/pages/qc_admission/up-selling.vue'),
      },

      {
        path: 'report-data-input',
        component: () => import('@/pages/qc_admission/report-data-input.vue'),
      },
    ],
  },

  {
    path: '/',
    component: () => import('@/layouts/blank.vue'),
    children: [
      {
        path: 'login',
        component: () => import('@/pages/login.vue'),
      },

      {
        path: 'register',
        component: () => import('@/pages/register.vue'),
      },

      {
        path: '/:pathMatch(.*)*',
        component: () => import('@/pages/[...error].vue'),
      },
    ],
  },
]