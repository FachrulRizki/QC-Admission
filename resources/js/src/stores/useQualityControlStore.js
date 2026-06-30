import { defineStore } from 'pinia'
import axios from 'axios'

export const useQualityControlStore = defineStore('qualityControl', {
  state: () => ({
    records:    [],
    loading:    false,
    error:      null,
    pagination: { page: 1, perPage: 20, total: 0, lastPage: 1 },
    filters: {
      search:    '',
      status:    null,
      petugas:   null,
      date_from: null,
      date_to:   null,
    },
  }),

  getters: {
    totalRecords:    (state) => state.pagination.total,
    isLoading:       (state) => state.loading,
    edukasiCount:    (state) => state.records.filter(r => r.status === 'Edukasi').length,
    lanjutanCount:   (state) => state.records.filter(r => r.status === 'Edukasi lanjutan').length,
  },

  actions: {
    async fetchRecords(params = {}) {
      this.loading = true
      this.error   = null
      try {
        const res = await axios.get('/api/quality-control', {
          params: { ...this.filters, ...params },
        })
        this.records              = res.data.data   ?? []
        this.pagination.total     = res.data.meta?.total    ?? this.records.length
        this.pagination.lastPage  = res.data.meta?.last_page ?? 1
      } catch (err) {
        this.error = err.response?.data?.message ?? 'Gagal memuat data Quality Control'
      } finally {
        this.loading = false
      }
    },

    async store(payload) {
      this.loading = true
      try {
        const res = await axios.post('/api/quality-control', payload)
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
        const res = await axios.put(`/api/quality-control/${id}`, payload)
        // Update record di state
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
        await axios.delete(`/api/quality-control/${id}`)
        this.records = this.records.filter(r => r.id !== id)
        return { success: true }
      } catch (err) {
        return { success: false, message: err.response?.data?.message ?? 'Gagal hapus data' }
      }
    },

    setFilter(key, value) { this.filters[key] = value },

    resetFilters() {
      this.filters = { search: '', status: null, petugas: null, date_from: null, date_to: null }
    },
  },
})
