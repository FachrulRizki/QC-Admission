import { defineStore } from 'pinia'
import axios from 'axios'

export const useAuthStore = defineStore('auth', {
  state: () => ({
    user:            null,
    token:           null,
    isAuthenticated: false,
  }),

  getters: {
    currentUser:   (state) => state.user,
    isLoggedIn:    (state) => state.isAuthenticated,
    userRole:      (state) => state.user?.role ?? null,
    isAdmin:       (state) => state.user?.role === 'admin',
    isQcAdmission: (state) => state.user?.role === 'qc_admission',
    isKasir:       (state) => state.user?.role === 'kasir',
    canAccessMain: (state) => ['admin', 'qc_admission'].includes(state.user?.role),
  },

  actions: {
    async login(credentials) {
      try {
        const res = await axios.post('/api/auth/login', credentials)
        const { user, token } = res.data
        this._setSession(user, token)
        return { success: true, role: user.role }
      } catch (err) {
        return { success: false, message: err.response?.data?.message ?? 'Login gagal.' }
      }
    },

    async ssoLogin(keycloakAccessToken) {
      try {
        const res = await axios.post('/api/auth/sso/callback', { access_token: keycloakAccessToken })
        const { user, token } = res.data
        this._setSession(user, token)
        return { success: true, role: user.role }
      } catch (err) {
        return { success: false, message: err.response?.data?.message ?? 'SSO login gagal.' }
      }
    },

    async logout() {
      try { await axios.post('/api/auth/logout') } catch {}
      this._clearSession()
    },

    async fetchMe() {
      try {
        const res = await axios.get('/api/auth/me')
        this.user = res.data.user
        this.isAuthenticated = true
        localStorage.setItem('qc_user', JSON.stringify(res.data.user))
      } catch {
        // Jangan clear session — biarkan pakai data lokal
        // _clearSession hanya dipanggil saat logout eksplisit atau 401 interceptor
      }
    },

    /**
     * Restore session dari localStorage tanpa harus menunggu server.
     * Jika token ada → anggap sudah auth, pakai user dari localStorage.
     * Refresh dari server dilakukan di background (tidak blocking).
     */
    async restoreSession() {
      const token = localStorage.getItem('qc_token')
      const storedUser = localStorage.getItem('qc_user')

      if (!token) return

      // Set state dari localStorage dulu — menu langsung tampil
      this.token           = token
      this.isAuthenticated = true
      _setAxiosDefaults(token)

      if (storedUser) {
        try { this.user = JSON.parse(storedUser) } catch {}
      }

      // Refresh dari server di background — tidak blocking mount
      this.fetchMe().catch(() => {})
    },

    _setSession(user, token) {
      this.user            = user
      this.token           = token
      this.isAuthenticated = true
      localStorage.setItem('qc_token', token)
      localStorage.setItem('qc_user', JSON.stringify(user))
      _setAxiosDefaults(token)
    },

    _clearSession() {
      this.user            = null
      this.token           = null
      this.isAuthenticated = false
      localStorage.removeItem('qc_token')
      localStorage.removeItem('qc_user')
      _setAxiosDefaults(null)
    },
  },
})

export function setupAxiosInterceptors() {
  axios.defaults.baseURL = ''
  axios.defaults.headers.common['Accept']            = 'application/json'
  axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest'

  const existingToken = localStorage.getItem('qc_token')
  if (existingToken) {
    axios.defaults.headers.common['Authorization'] = `Bearer ${existingToken}`
  }

  axios.interceptors.request.use(config => {
    const token = localStorage.getItem('qc_token')
    if (token) config.headers['Authorization'] = `Bearer ${token}`
    return config
  })

  axios.interceptors.response.use(
    res => res,
    err => {
      if (err.response?.status === 401 && !window.location.pathname.includes('/login')) {
        localStorage.removeItem('qc_token')
        localStorage.removeItem('qc_user')
        window.location.href = '/login'
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
