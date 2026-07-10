import { defineStore } from 'pinia'
import { usePage } from '@inertiajs/vue3'
import axios from 'axios'

// Auth dibaca dari Inertia shared props (session Laravel), bukan localStorage
export const useAuthStore = defineStore('auth', {
  getters: {
    user:          () => usePage().props.auth?.user  ?? null,
    roles:         () => usePage().props.auth?.roles ?? [],
    isLoggedIn:    () => !!usePage().props.auth?.user,
    isAdmin:       () => usePage().props.auth?.roles?.includes('admin')        ?? false,
    isQcAdmission: () => usePage().props.auth?.roles?.includes('qc_admission') ?? false,
    isKasir:       () => usePage().props.auth?.roles?.includes('kasir')        ?? false,
    canAccessMain: () => {
      const roles = usePage().props.auth?.roles ?? []
      return roles.includes('admin') || roles.includes('qc_admission')
    },
  },

  actions: {
    // Login lokal — simpan session lalu full-page redirect
    async login(credentials) {
      try {
        const res    = await axios.post('/auth/login', credentials)
        const target = _defaultRouteForRoles(res.data.user?.roles ?? [])
        window.location.href = target
        return { success: true, user: res.data.user }
      } catch (err) {
        return { success: false, message: err.response?.data?.message ?? 'Login gagal.' }
      }
    },

    // Logout — back-channel Keycloak + clear session
    async logout() {
      try {
        await axios.post('/auth/keycloak/logout')
      } catch {}
      window.location.href = '/login'
    },
  },
})

// Setup axios — session cookie + CSRF, tanpa Bearer token
export function setupAxiosDefaults() {
  axios.defaults.withCredentials = true
  axios.defaults.headers.common['Accept']            = 'application/json'
  axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest'

  axios.interceptors.request.use(config => {
    const token = _getCsrfToken()
    if (token) config.headers['X-CSRF-TOKEN'] = token
    return config
  })

  // Redirect ke /login jika session expired
  axios.interceptors.response.use(
    res => res,
    err => {
      const status = err.response?.status
      if ((status === 401 || status === 419) && !window.location.pathname.includes('/login')) {
        window.location.href = '/login'
      }
      return Promise.reject(err)
    }
  )
}

function _getCsrfToken() {
  return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
    ?? document.cookie.match(/XSRF-TOKEN=([^;]+)/)?.[1]
    ?? ''
}

function _defaultRouteForRoles(roles) {
  return roles.includes('kasir') ? '/view-data-input' : '/dashboard'
}
