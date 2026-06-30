import { defineStore } from 'pinia'
import axios from 'axios'

export const useQualityControlStore = defineStore('qualityControl', {
  state: () => ({
    records: [],
    loading: false,
    error: null,
    pagination: {
      page: 1,
      perPage: 10,
      total: 0,
    },
    filters: {
      search: '',
      dateFrom: null,
      dateTo: null,
      status: null,
    },
  }),

  getters: {
    totalRecords: (state) => state.pagination.total,
    isLoading: (state) => state.loading,
  },

  actions: {
    async fetchRecords(params = {}) {
      this.loading = true
      this.error = null
      try {
        const response = await axios.get('/api/quality-control', {
          params: { ...this.filters, ...params },
        })
        this.records = response.data.data
        this.pagination.total = response.data.meta?.total ?? this.records.length
      } catch (err) {
        this.error = err.response?.data?.message ?? 'Gagal memuat data Quality Control'
      } finally {
        this.loading = false
      }
    },

    async store(payload) {
      this.loading = true
      this.error = null
      try {
        const response = await axios.post('/api/quality-control', payload)
        return { success: true, data: response.data }
      } catch (err) {
        this.error = err.response?.data?.message ?? 'Gagal menyimpan data'
        return { success: false, message: this.error }
      } finally {
        this.loading = false
      }
    },

    async update(id, payload) {
      this.loading = true
      try {
        const response = await axios.put(`/api/quality-control/${id}`, payload)
        return { success: true, data: response.data }
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

    setFilter(key, value) {
      this.filters[key] = value
    },

    resetFilters() {
      this.filters = { search: '', dateFrom: null, dateTo: null, status: null }
    },
  },
})
