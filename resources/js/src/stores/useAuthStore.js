import { defineStore } from 'pinia'

export const useAuthStore = defineStore('auth', {
  state: () => ({
    user: null,
    token: null,
    isAuthenticated: false,
  }),

  getters: {
    currentUser: (state) => state.user,
    isLoggedIn: (state) => state.isAuthenticated,
    userRole: (state) => state.user?.role ?? null,
  },

  actions: {
    async login(credentials) {
      try {
        const response = await fetch('/api/auth/login', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
          body: JSON.stringify(credentials),
        })
        const data = await response.json()

        if (!response.ok) throw new Error(data.message ?? 'Login gagal')

        this.user = data.user
        this.token = data.token
        this.isAuthenticated = true
        localStorage.setItem('qc_token', data.token)

        return { success: true }
      } catch (err) {
        return { success: false, message: err.message }
      }
    },

    logout() {
      this.user = null
      this.token = null
      this.isAuthenticated = false
      localStorage.removeItem('qc_token')
    },

    restoreSession() {
      const token = localStorage.getItem('qc_token')
      if (token) {
        this.token = token
        this.isAuthenticated = true
      }
    },
  },
})
