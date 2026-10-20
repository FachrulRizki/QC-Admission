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

        // Pastikan selalu array — respons tak terduga tidak boleh membuat render crash
        const list = v => (Array.isArray(v) ? v : (v && typeof v === 'object' ? Object.values(v) : []))

        this.stats             = data.stats ?? this.stats
        this.avgPerPetugas     = list(data.avg_per_petugas)
        this.matrix            = list(data.matrix)
        this.edukasiPerPetugas = list(data.edukasi_per_petugas)
        this.perStatus         = list(data.per_status)
        this.perKamar          = list(data.per_kamar)
        this.recentQC          = list(data.recent_qc)
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
