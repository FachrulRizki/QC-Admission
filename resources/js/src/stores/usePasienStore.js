import { defineStore } from 'pinia'
import axios from 'axios'

/**
 * usePasienStore
 * Search pasien rawat inap dari DB RSUS (via Laravel backend).
 * Mendukung:
 *   - search(query)  — ketik min 2 karakter untuk cari No_Reg / No_MR / Nama
 *   - lookup(no_reg) — ambil satu pasien untuk autofill form
 */
export const usePasienStore = defineStore('pasien', {
  state: () => ({
    results:     [],   // hasil search terakhir
    loading:     false,
    source:      null, // 'rsus_db' | 'mock' | 'mock_fallback'
    lastQuery:   '',
  }),

  getters: {
    // Format untuk VAutocomplete :items
    optionList: (state) => state.results.map(p => ({
      title: p.label ?? (p.no_reg + ' — ' + p.nama_pasien),
      value: p.no_reg,
      data:  p,
    })),
  },

  actions: {
    /**
     * Cari pasien berdasarkan teks bebas.
     * Debounce dilakukan di komponen (watch + setTimeout).
     */
    async search(query) {
      const q = (query ?? '').trim()
      if (q.length < 2) {
        this.results   = []
        this.lastQuery = ''
        return
      }
      if (q === this.lastQuery) return

      this.loading   = true
      this.lastQuery = q
      try {
        const { data } = await axios.get('/api/pasien', { params: { search: q } })
        this.results = data.data ?? []
        this.source  = data.source ?? 'mock'
      } catch (err) {
        console.warn('[usePasienStore] search gagal:', err.message)
        this.results = []
      } finally {
        this.loading = false
      }
    },

    /**
     * Ambil satu pasien berdasarkan No_Reg — untuk autofill form.
     * Return objek pasien atau null.
     */
    async lookup(noReg) {
      if (!noReg) return null
      try {
        const { data } = await axios.get('/api/pasien', { params: { no_reg: noReg } })
        const list = data.data ?? []
        return list[0] ?? null
      } catch {
        return null
      }
    },

    clear() {
      this.results   = []
      this.lastQuery = ''
    },
  },
})
