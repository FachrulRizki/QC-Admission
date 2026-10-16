import { defineStore } from 'pinia'
import axios from 'axios'

export const useBooklistAdmissionStore = defineStore('booklistAdmission', {
  state: () => ({
    records: [],
    loading: false,
    error: null,
    pagination: { page: 1, perPage: 100, total: 0 },
  }),

  getters: {
    totalRecords: (state) => state.pagination.total,
    bedahCount:   (state) => (state.records ?? []).filter(r => r.status_pasien === 'Bedah').length,
    nonBedahCount:(state) => (state.records ?? []).filter(r => r.status_pasien === 'Non Bedah').length,
  },

  actions: {
    async fetchRecords(params = {}) {
      this.loading = true
      this.error   = null
      try {
        const response = await axios.get('/api/booklist-admission', { params })
        this.records             = response.data.data
        this.pagination.total    = response.data.meta?.total ?? this.records.length
      } catch (err) {
        this.error = err.response?.data?.message ?? 'Gagal memuat data Booklist Admission'
      } finally {
        this.loading = false
      }
    },

    async store(payload) {
      this.loading = true
      this.error   = null
      try {
        const response = await axios.post('/api/booklist-admission', payload)
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
      this.error   = null
      try {
        const response = await axios.put(`/api/booklist-admission/${id}`, payload)
        const idx = this.records.findIndex(r => r.id === id)
        if (idx !== -1) {
          this.records.splice(idx, 1, response.data.data ?? { ...this.records[idx], ...payload })
        }
        return { success: true, data: response.data }
      } catch (err) {
        this.error = err.response?.data?.message ?? 'Gagal mengubah data'
        return { success: false, message: this.error }
      } finally {
        this.loading = false
      }
    },

    async destroy(id) {
      try {
        await axios.delete(`/api/booklist-admission/${id}`)
        this.records = this.records.filter(r => r.id !== id)
        return { success: true }
      } catch (err) {
        return { success: false, message: err.response?.data?.message ?? 'Gagal hapus data' }
      }
    },
  },
})
