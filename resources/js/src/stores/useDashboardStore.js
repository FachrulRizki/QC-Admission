import { defineStore } from 'pinia'
import axios from 'axios'

export const useDashboardStore = defineStore('dashboard', {
  state: () => ({
    stats: {
      jumlahEdukasiPasien:  0,
      durasiTungguEdukasi:  '0',
      totalEdukasi:         0,
      totalEdukasiLanjutan: 0,
      totalBatalRanap:      0,
      totalUpSelling:       0,
    },
    avgPerPetugas:     [],
    matrix:            [],
    edukasiPerPetugas: [],
    perStatus:         [],
    perKamar:          [],
    recentQC:          [],
    dateFrom:          null,  // Y-m-d
    dateTo:            null,  // Y-m-d
    loading:           false,
    error:             null,
  }),

  actions: {
    async fetchDashboard(dateFrom = null, dateTo = null) {
      this.loading = true
      this.error   = null
      try {
        const params = {}
        if (dateFrom) params.date_from = dateFrom
        if (dateTo)   params.date_to   = dateTo

        const { data } = await axios.get('/api/dashboard', { params })

        this.stats             = data.stats             ?? this.stats
        this.avgPerPetugas     = data.avg_per_petugas   ?? []
        this.matrix            = data.matrix            ?? []
        this.edukasiPerPetugas = data.edukasi_per_petugas ?? []
        this.perStatus         = data.per_status        ?? []
        this.perKamar          = data.per_kamar         ?? []
        this.recentQC          = data.recent_qc         ?? []
        this.dateFrom          = data.date_from         ?? dateFrom
        this.dateTo            = data.date_to           ?? dateTo
      } catch (err) {
        this.error = err.response?.data?.message ?? 'Gagal memuat data dashboard'
      } finally {
        this.loading = false
      }
    },
  },
})
