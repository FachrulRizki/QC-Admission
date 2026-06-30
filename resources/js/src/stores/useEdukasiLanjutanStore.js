import { defineStore } from 'pinia'
import axios from 'axios'

export const useEdukasiLanjutanStore = defineStore('edukasiLanjutan', {
  state: () => ({
    records: [],
    loading: false,
    error: null,
    pagination: { page: 1, perPage: 20, total: 0 },
    filters: { search: '', month: null, year: null },
  }),

  getters: {
    groupedByMonth: (state) => {
      const groups = {}
      state.records.forEach(record => {
        const key = record.bulan ?? 'Lainnya'
        if (!groups[key]) groups[key] = []
        groups[key].push(record)
      })
      return groups
    },
    totalRecords: (state) => state.pagination.total,
  },

  actions: {
    async fetchRecords(params = {}) {
      this.loading = true
      this.error = null
      try {
        const response = await axios.get('/api/edukasi-lanjutan', {
          params: { ...this.filters, ...params },
        })
        this.records = response.data.data
        this.pagination.total = response.data.meta?.total ?? this.records.length
      } catch (err) {
        this.error = err.response?.data?.message ?? 'Gagal memuat data Edukasi Lanjutan'
      } finally {
        this.loading = false
      }
    },

    async store(payload) {
      this.loading = true
      this.error = null
      try {
        const response = await axios.post('/api/edukasi-lanjutan', payload)
        return { success: true, data: response.data }
      } catch (err) {
        this.error = err.response?.data?.message ?? 'Gagal menyimpan data'
        return { success: false, message: this.error }
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
  },
})
