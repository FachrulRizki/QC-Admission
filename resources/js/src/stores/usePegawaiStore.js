import { defineStore } from 'pinia'
import axios from 'axios'

/**
 * usePegawaiStore
 * Ambil daftar petugas dari KPI API (via Laravel backend).
 * Di-cache di store — satu kali fetch per session.
 */
export const usePegawaiStore = defineStore('pegawai', {
  state: () => ({
    items:   [],   // [{ id, nama, nip, jabatan }]
    loading: false,
    source:  null, // 'kpi_api' | 'fallback'
    fetched: false,
  }),

  getters: {
    // Array nama saja — untuk VAutocomplete :items="namaList"
    namaList: (state) => state.items.map(p => p.nama),

    // Array objek lengkap — untuk autocomplete dengan label
    optionList: (state) => state.items.map(p => ({
      title: p.nama,
      value: p.nama,
      nip:   p.nip,
    })),
  },

  actions: {
    async fetch(force = false) {
      if (this.fetched && !force) return

      this.loading = true
      try {
        const { data } = await axios.get('/api/pegawai')
        this.items   = data.data ?? []
        this.source  = data.source ?? 'kpi_api'
        this.fetched = true
      } catch (err) {
        console.warn('[usePegawaiStore] Gagal fetch pegawai:', err.message)
        // fallback lokal
        this.items = [
          { id: 1, nama: 'Nurul',           nip: null, jabatan: 'Customer Care' },
          { id: 2, nama: 'AYU Putri Anisa', nip: null, jabatan: 'Customer Care' },
          { id: 3, nama: 'Reskim',          nip: null, jabatan: 'Customer Care' },
          { id: 4, nama: 'Mulbagus Koyum',  nip: null, jabatan: 'Customer Care' },
          { id: 5, nama: 'Abdul Hayyi',     nip: null, jabatan: 'Customer Care' },
        ]
        this.source  = 'fallback'
        this.fetched = true
      } finally {
        this.loading = false
      }
    },

    reset() {
      this.items   = []
      this.fetched = false
      this.source  = null
    },
  },
})
