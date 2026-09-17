import { defineStore } from 'pinia'
import axios from 'axios'

export const useAlasanStore = defineStore('alasan', {
  state: () => ({
    records:    [],
    loading:    false,
    error:      null,
    pagination: { page: 1, perPage: 10, total: 0 },
    filters:    { search: '', dateFrom: null, dateTo: null },
  }),

  actions: {
    async fetchRecords(params = {}) {
      this.loading = true
      this.error   = null
      try {
        const { data } = await axios.get('/api/alasan', { params: { ...this.filters, ...params } })
        this.records            = data.data ?? []
        this.pagination.total   = data.meta?.total ?? this.records.length
      } catch (err) {
        this.error = err.response?.data?.message ?? 'Gagal memuat data Alasan'
      } finally {
        this.loading = false
      }
    },

    async store(payload) {
      this.loading = true
      this.error   = null
      try {
        const { data } = await axios.post('/api/alasan', payload)
        return { success: true, data }
      } catch (err) {
        this.error = err.response?.data?.message ?? 'Gagal menyimpan data'
        return { success: false, message: this.error }
      } finally {
        this.loading = false
      }
    },

    async update(id, payload) {
      this.loading = true
      this.error   = null
      try {
        const { data } = await axios.put(`/api/alasan/${id}`, payload)
        const idx = this.records.findIndex(r => r.id === id)
        if (idx !== -1) this.records.splice(idx, 1, data.data ?? { ...this.records[idx], ...payload })
        return { success: true, data }
      } catch (err) {
        this.error = err.response?.data?.message ?? 'Gagal update data'
        return { success: false, message: this.error }
      } finally {
        this.loading = false
      }
    },

    async destroy(id) {
      try {
        await axios.delete(`/api/alasan/${id}`)
        this.records = this.records.filter(r => r.id !== id)
        return { success: true }
      } catch (err) {
        return { success: false, message: err.response?.data?.message ?? 'Gagal hapus data' }
      }
    },
  },
})
