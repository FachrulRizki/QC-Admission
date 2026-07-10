import { defineStore } from 'pinia'
import axios from 'axios'

/**
 * usePasienStore
 * Search pasien rawat inap dari DB RSUS (via Laravel backend).
 */
export const usePasienStore = defineStore('pasien', {
  state: () => ({
    results:   [],
    loading:   false,
    source:    null,
  }),

  getters: {
    // Format untuk VAutocomplete :items
    optionList: (state) => (state.results ?? []).filter(Boolean).map(p => ({
      title: (p.no_reg ?? '') + ' — ' + (p.nama_pasien ?? ''),
      value: p.no_reg,
      data:  p,
    })),
  },

  actions: {
    /**
     * Cari pasien berdasarkan teks bebas.
     * Tidak ada guard lastQuery — boleh search ulang kapan saja.
     */
    async search(query) {
      const q = (query ?? '').trim()
      if (q.length < 2) {
        this.results = []
        return
      }
      this.loading = true
      try {
        const { data } = await axios.get('/api/pasien', { params: { search: q } })
        this.results = data.data ?? []
        this.source  = data.source ?? 'mock'
      } catch (err) {
        console.warn('[usePasienStore] search error:', err.message)
        this.results = []
      } finally {
        this.loading = false
      }
    },

    /**
     * Ambil satu pasien berdasarkan No_Reg untuk autofill form.
     */
    async lookup(noReg) {
      if (!noReg) return null
      try {
        const { data } = await axios.get('/api/pasien', { params: { no_reg: noReg } })
        return (data.data ?? [])[0] ?? null
      } catch {
        return null
      }
    },

    clear() {
      this.results = []
    },
  },
})
