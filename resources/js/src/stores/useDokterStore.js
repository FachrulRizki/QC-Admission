import { defineStore } from 'pinia'
import axios from 'axios'

export const useDokterStore = defineStore('dokter', {
  state: () => ({
    results: [],
    loading: false,
  }),

  getters: {
    optionList: (state) => (state.results ?? []).map(d => ({
      title: d.label ?? d.nama,
      value: d.nama,   // yang disimpan ke DB adalah nama dokter
      data:  d,
    })),
  },

  actions: {
    async search(query) {
      const q = (query ?? '').trim()
      if (q.length < 2) { this.results = []; return }

      this.loading = true
      try {
        const { data } = await axios.get('/api/dokter/search', { params: { q } })
        this.results = data.data ?? []
      } catch (err) {
        console.warn('[useDokterStore] search error:', err.message)
        this.results = []
      } finally {
        this.loading = false
      }
    },

    clear() {
      this.results = []
    },
  },
})
