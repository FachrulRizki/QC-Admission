import { defineStore } from 'pinia'
import axios from 'axios'

export const useDashboardStore = defineStore('dashboard', {
  state: () => ({
    stats: {
      jumlahEksternalPasien: 0,
      durasiTungguEdukasi: '00:00',
      totalQC: 0,
      totalBatalRanap: 0,
      totalEdukasiLanjutan: 0,
      totalUpSelling: 0,
    },
    recentQC: [],
    petugasStats: [],
    ruanganStats: [],
    loading: false,
    error: null,
  }),

  actions: {
    async fetchDashboard() {
      this.loading = true
      this.error = null
      try {
        const response = await axios.get('/api/dashboard')
        const data = response.data

        this.stats = data.stats ?? this.stats
        this.recentQC = data.recent_qc ?? []
        this.petugasStats = data.petugas_stats ?? []
        this.ruanganStats = data.ruangan_stats ?? []
      } catch (err) {
        this.error = err.response?.data?.message ?? 'Gagal memuat data dashboard'
      } finally {
        this.loading = false
      }
    },
  },
})
