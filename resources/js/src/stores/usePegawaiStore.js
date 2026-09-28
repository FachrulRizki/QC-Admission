import { defineStore } from 'pinia'
import axios from 'axios'

/**
 * usePegawaiStore
 * - fetch()      → pegawai Customer Care (untuk field "Petugas")
 * - fetchSemua() → SEMUA pegawai RS tanpa filter departemen (untuk "Rekomendasi Karyawan RS")
 */
export const usePegawaiStore = defineStore('pegawai', {
  state: () => ({
    // Field Petugas — Customer Care only
    items:        [],
    loading:      false,
    source:       null,
    fetched:      false,

    // Field Rekomendasi Karyawan RS — semua departemen
    semuaItems:   [],
    semuaLoading: false,
    semuaFetched: false,
  }),

  getters: {
    // Array nama saja — untuk field Petugas
    namaList: (state) => state.items.filter(Boolean).map(p => p.nama),

    optionList: (state) => state.items.filter(Boolean).map(p => ({
      title: p.nama,
      value: p.nama,
      nip:   p.nip,
    })),
  },

  actions: {
    // ── Fetch pegawai Customer Care (field Petugas) ────────────────────────
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

    // ── Fetch SEMUA pegawai RS (field Rekomendasi Karyawan) ───────────────
    async fetchSemua(force = false) {
      if (this.semuaFetched && !force) return

      this.semuaLoading = true
      try {
        const { data } = await axios.get('/api/pegawai/semua')
        this.semuaItems   = data.data ?? []
        this.semuaFetched = true
      } catch (err) {
        console.warn('[usePegawaiStore] Gagal fetch semua pegawai:', err.message)
        // fallback: pakai items Customer Care kalau ada, atau kosong
        this.semuaItems   = this.items.length ? this.items : []
        this.semuaFetched = true
      } finally {
        this.semuaLoading = false
      }
    },

    reset() {
      this.items        = []
      this.fetched      = false
      this.source       = null
      this.semuaItems   = []
      this.semuaFetched = false
    },
  },
})
