import { defineStore } from 'pinia'
import axios from 'axios'

export const useAuthStore = defineStore('auth', {
  state: () => ({
    user: null,
    token: null,
    isAuthenticated: false,
  }),

  getters: {
    currentUser:   (state) => state.user,
    isLoggedIn:    (state) => state.isAuthenticated,
    userRole:      (state) => state.user?.role ?? null,
    isAdmin:       (state) => ['admin', 'supervisor'].includes(state.user?.role),
  },

  actions: {
    // ── Local login ─────────────────────────────────────────────────────────
    async login(credentials) {
      try {
        const response = await axios.post('/api/auth/login', credentials)
        const { user, token } = response.data

        this._setSession(user, token)
        return { success: true }
      } catch (err) {
        return { success: false, message: err.response?.data?.message ?? 'Login gagal.' }
      }
    },

    // ── SSO / Keycloak login ─────────────────────────────────────────────────
    // Call this after obtaining the Keycloak access_token in the frontend.
    async ssoLogin(keycloakAccessToken) {
      try {
        const response = await axios.post('/api/auth/sso/callback', {
          access_token: keycloakAccessToken,
        })
        const { user, token } = response.data

        this._setSession(user, token)
        return { success: true }
      } catch (err) {
        return { success: false, message: err.response?.data?.message ?? 'SSO login gagal.' }
      }
    },

    async logout() {
      try {
        await axios.post('/api/auth/logout')
      } catch {}

      this._clearSession()
    },

    async fetchMe() {
      try {
        const response = await axios.get('/api/auth/me')
        this.user = response.data.user
        this.isAuthenticated = true
      } catch {
        this._clearSession()
      }
    },

    async restoreSession() {
      const token = localStorage.getItem('qc_token')
      if (!token) return

      this.token = token
      this.isAuthenticated = true
      _setAxiosDefaults(token)

      // Try to refresh user data from server
      await this.fetchMe()
    },

    _setSession(user, token) {
      this.user = user
      this.token = token
      this.isAuthenticated = true
      localStorage.setItem('qc_token', token)
      _setAxiosDefaults(token)
    },

    _clearSession() {
      this.user = null
      this.token = null
      this.isAuthenticated = false
      localStorage.removeItem('qc_token')
      _setAxiosDefaults(null)
    },
  },
})

/**
 * Configure axios defaults and interceptors.
 * Called once on app boot and on login/logout.
 */
export function setupAxiosInterceptors() {
  // Saat pakai Laragon/Apache, baseURL kosong agar request ke origin yang sama
  axios.defaults.baseURL = ''
  axios.defaults.headers.common['Accept'] = 'application/json'
  axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest'

  // Pasang token yang ada di localStorage sejak awal
  const existingToken = localStorage.getItem('qc_token')
  if (existingToken) {
    axios.defaults.headers.common['Authorization'] = `Bearer ${existingToken}`
  }

  // Request interceptor — selalu update token terbaru
  axios.interceptors.request.use(config => {
    const token = localStorage.getItem('qc_token')
    if (token) {
      config.headers['Authorization'] = `Bearer ${token}`
    }
    return config
  })

  // Response interceptor — 401 → redirect ke login
  axios.interceptors.response.use(
    res => res,
    err => {
      if (err.response?.status === 401) {
        // Jangan redirect kalau sedang di halaman login
        if (!window.location.pathname.includes('/login')) {
          localStorage.removeItem('qc_token')
          window.location.href = '/login'
        }
      }
      return Promise.reject(err)
    }
  )
}

function _setAxiosDefaults(token) {
  if (token) {
    axios.defaults.headers.common['Authorization'] = `Bearer ${token}`
  } else {
    delete axios.defaults.headers.common['Authorization']
  }
}
