import { defineStore } from 'pinia'
import axios from 'axios'

export const useBatalRanapStore = defineStore('batalRanap', {
  state: () => ({
    records: [],
    loading: false,
    error: null,
    pagination: { page: 1, perPage: 10, total: 0 },
    filters: { search: '', dateFrom: null, dateTo: null, statusOk: null },
  }),

  getters: {
    totalRecords: (state) => state.pagination.total,
    pendingVerification: (state) => state.records.filter(r => !r.status_ok),
  },

  actions: {
    async fetchRecords(params = {}) {
      this.loading = true
      this.error = null
      try {
        const response = await axios.get('/api/batal-ranap', {
          params: { ...this.filters, ...params },
        })
        this.records = response.data.data
        this.pagination.total = response.data.meta?.total ?? this.records.length
      } catch (err) {
        this.error = err.response?.data?.message ?? 'Gagal memuat data Batal Ranap'
      } finally {
        this.loading = false
      }
    },

    async store(payload) {
      this.loading = true
      this.error = null
      try {
        const response = await axios.post('/api/batal-ranap', payload)
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
      this.error = null
      try {
        const response = await axios.put(`/api/batal-ranap/${id}`, payload)
        const idx = this.records.findIndex(r => r.id === id)
        if (idx !== -1) this.records.splice(idx, 1, response.data.data ?? { ...this.records[idx], ...payload })
        return { success: true, data: response.data }
      } catch (err) {
        this.error = err.response?.data?.message ?? 'Gagal update data'
        return { success: false, message: this.error }
      } finally {
        this.loading = false
      }
    },

    async konfirmasiClosing(id, statusClosing) {
      try {
        const response = await axios.patch(`/api/batal-ranap/${id}/konfirmasi-closing`, {
          status_closing: statusClosing,
        })
        const idx = this.records.findIndex(r => r.id === id)
        if (idx !== -1) this.records[idx] = { ...this.records[idx], status_closing: statusClosing }
        return { success: true, data: response.data }
      } catch (err) {
        return { success: false, message: err.response?.data?.message ?? 'Gagal konfirmasi closing' }
      }
    },

    async verifikasi(id, payload) {
      try {
        const response = await axios.patch(`/api/batal-ranap/${id}/verifikasi`, payload)
        return { success: true, data: response.data }
      } catch (err) {
        return { success: false, message: err.response?.data?.message ?? 'Gagal verifikasi data' }
      }
    },

    async destroy(id) {
      try {
        await axios.delete(`/api/batal-ranap/${id}`)
        this.records = this.records.filter(r => r.id !== id)
        return { success: true }
      } catch (err) {
        return { success: false, message: err.response?.data?.message ?? 'Gagal hapus data' }
      }
    },
  },
})
