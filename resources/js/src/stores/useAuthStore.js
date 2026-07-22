import { defineStore } from 'pinia'
import { usePage } from '@inertiajs/vue3'
import axios from 'axios'

/**
 * useAuthStore — satu-satunya sumber kebenaran auth di frontend.
 *
 * Roles dan permissions berasal dari Keycloak (via Inertia shared props).
 * Tidak ada hardcode role/permission di frontend — semua dicek terhadap
 * array yang dikirim server.
 */
export const useAuthStore = defineStore('auth', {
  getters: {
    // ── Data user ──────────────────────────────────────────────────────────
    user:        () => usePage().props.auth?.user        ?? null,
    roles:       () => usePage().props.auth?.roles       ?? [],
    permissions: () => usePage().props.auth?.permissions ?? [],
    isLoggedIn:  () => !!usePage().props.auth?.user,

    // ── Role helpers (baca dari Keycloak, bukan hardcode) ──────────────────
    isAdmin:       () => usePage().props.auth?.roles?.includes('admin')        ?? false,
    isQcAdmission: () => usePage().props.auth?.roles?.includes('qc_admission') ?? false,
    isKasir:       () => usePage().props.auth?.roles?.includes('kasir')        ?? false,

    // ── Akses menu utama — cek permission dashboard:view ──────────────────
    canAccessMain: () => {
      const perms = usePage().props.auth?.permissions ?? []
      return perms.includes('dashboard:view') || perms.includes('quality-control:view')
    },
  },

  actions: {
    /**
     * Cek apakah user punya role tertentu.
     * Nilai roles datang dari Keycloak — sesuaikan nama role dengan konfigurasi realm.
     */
    hasRole(role) {
      const roles = usePage().props.auth?.roles ?? []
      if (Array.isArray(role)) return role.some(r => roles.includes(r))
      return roles.includes(role)
    },

    /**
     * Cek permission granular dari Keycloak (UMA atau derived dari role).
     * Format: 'resource:scope' contoh 'quality-control:write'
     */
    hasPermission(permission) {
      const perms = usePage().props.auth?.permissions ?? []
      if (Array.isArray(permission)) return permission.some(p => perms.includes(p))
      return perms.includes(permission)
    },

    /**
     * Cek satu atau lebih role ATAU permission sekaligus.
     * Berguna untuk guard route dan tombol UI.
     */
    can(roleOrPermission) {
      return this.hasRole(roleOrPermission) || this.hasPermission(roleOrPermission)
    },

    /**
     * Logout — redirect langsung ke /auth/logout (GET).
     * Backend flush session + back-channel Keycloak, lalu redirect ke /login.
     */
    logout() {
      // GET request — tidak perlu axios/CSRF, browser langsung follow redirect server
      window.location.replace('/auth/logout')
    },
  },
})

/**
 * Setup axios defaults — session cookie + CSRF.
 * Tidak ada Bearer token karena auth berbasis session SSO.
 */
export function setupAxiosDefaults() {
  axios.defaults.withCredentials = true
  axios.defaults.headers.common['Accept']            = 'application/json'
  axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest'

  axios.interceptors.request.use(config => {
    const token = _getCsrfToken()
    if (token) config.headers['X-CSRF-TOKEN'] = token
    return config
  })

  axios.interceptors.response.use(
    res => res,
    err => {
      const status = err.response?.status
      // Session expired atau belum login
      if ((status === 401 || status === 419) && !window.location.pathname.includes('/login')) {
        window.location.href = '/login'
      }
      return Promise.reject(err)
    },
  )
}

function _getCsrfToken() {
  return (
    document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
    ?? document.cookie.match(/XSRF-TOKEN=([^;]+)/)?.[1]
    ?? ''
  )
}
