import { defineStore } from 'pinia'
import axios from 'axios'

export const useEdukasiLanjutanStore = defineStore('edukasiLanjutan', {
  state: () => ({
    records:    [],
    loading:    false,
    syncing:    false,
    error:      null,
    pagination: { page: 1, perPage: 20, total: 0, lastPage: 1 },
    filters: {
      search:  '',
      month:   null,
      year:    null,
      status:  null,
      petugas: null,
    },
  }),

  getters: {
    totalRecords:  (state) => state.pagination.total,
    menungguCount: (state) => state.records.filter(r => r.status === 'Menunggu').length,
    selesaiCount:  (state) => state.records.filter(r => r.status === 'Selesai').length,

    groupedByBulan: (state) => {
      const groups = {}
      state.records.forEach(r => {
        const key = r.bulan ?? 'Lainnya'
        if (!groups[key]) groups[key] = []
        groups[key].push(r)
      })
      return groups
    },
  },

  actions: {
    async fetchRecords(params = {}) {
      this.loading = true
      this.error   = null
      this.records = []   // reset dulu agar tidak tampil data lama saat loading
      try {
        // params dari caller (halaman) selalu override store filters
        // Hapus key yang null/undefined dari store filters agar tidak override params
        const baseFilters = Object.fromEntries(
          Object.entries(this.filters).filter(([, v]) => v !== null && v !== undefined && v !== '')
        )
        const res = await axios.get('/api/edukasi-lanjutan', {
          params: { ...baseFilters, ...params },
        })
        this.records             = res.data.data   ?? []
        this.pagination.total    = res.data.meta?.total    ?? this.records.length
        this.pagination.lastPage = res.data.meta?.last_page ?? 1
      } catch (err) {
        this.error = err.response?.data?.message ?? 'Gagal memuat data Edukasi Lanjutan'
      } finally {
        this.loading = false
      }
    },

    async store(payload) {
      this.loading = true
      try {
        const res = await axios.post('/api/edukasi-lanjutan', payload)
        return { success: true, data: res.data }
      } catch (err) {
        return { success: false, message: err.response?.data?.message ?? 'Gagal menyimpan data' }
      } finally {
        this.loading = false
      }
    },

    async update(id, payload) {
      this.loading = true
      try {
        const res = await axios.put(`/api/edukasi-lanjutan/${id}`, payload)
        const idx = this.records.findIndex(r => r.id === id)
        if (idx !== -1) this.records.splice(idx, 1, res.data.data ?? { ...this.records[idx], ...payload })
        return { success: true, data: res.data }
      } catch (err) {
        return { success: false, message: err.response?.data?.message ?? 'Gagal update data' }
      } finally {
        this.loading = false
      }
    },

    async destroy(id) {
      try {
        await axios.delete(`/api/edukasi-lanjutan/${id}`)
        this.records = this.records.filter(r => r.id !== id)
        return { success: true }
      } catch (err) {
        return { success: false, message: err.response?.data?.message ?? 'Gagal hapus data' }
      }
    },

    /**
     * Sync status dari DB RSUS via backend.
     * Pasien yang sudah mendapat bed → status otomatis Selesai.
     */
    async syncFromRsus() {
      this.syncing = true
      try {
        await axios.get('/api/edukasi-lanjutan/sync-rsus')
        // Reload setelah sync
        await this.fetchRecords()
        return { success: true }
      } catch (err) {
        return { success: false, message: err.response?.data?.message ?? 'Sync RSUS gagal' }
      } finally {
        this.syncing = false
      }
    },

    setFilter(key, value) { this.filters[key] = value },

    resetFilters() {
      this.filters = { search: '', month: null, year: null, status: null, petugas: null }
    },
  },
})
