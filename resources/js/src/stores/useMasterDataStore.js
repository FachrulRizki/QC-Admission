import { defineStore } from 'pinia'
import axios from 'axios'

/**
 * useMasterDataStore
 * Fetch semua master data dari /api/master-data dan cache di state.
 * Semua form pakai store ini untuk dropdown.
 *
 * Keys dari API:
 *   ket_bayar, ruangan, kelas, bangsal, keterangan_batal,
 *   status_ok, ket_up_selling, status_ket_qc, note_kamar,
 *   cara_masuk, diagnosa, jaminan
 */
export const useMasterDataStore = defineStore('masterData', {
  state: () => ({
    // Semua kategori dari API
    ket_bayar:        [],
    ruangan:          [],
    kelas:            [],
    bangsal:          [],
    keterangan_batal: [],
    status_ok:        [],
    ket_up_selling:   [],
    status_ket_qc:    [],
    note_kamar:       [],
    cara_masuk:       [],
    diagnosa:         [],
    jaminan:          [],

    loading: false,
    fetched: false,
    error:   null,
  }),

  getters: {
    // Shorthand getters yang sering dipakai di form
    keteranganBatalList: (s) => s.keterangan_batal,
    ruanganList:         (s) => s.ruangan,
    kelasList:           (s) => s.kelas,
    bangsalList:         (s) => s.bangsal,
    statusOkList:        (s) => s.status_ok,
    ketUpSellingList:    (s) => s.ket_up_selling,
    noteKamarList:       (s) => s.note_kamar,
    jaminanList:         (s) => s.jaminan,
    diagnosaList:        (s) => s.diagnosa,
    caraMasukList:       (s) => s.cara_masuk,
    ketBayarList:        (s) => s.ket_bayar,
    statusKetQcList:     (s) => s.status_ket_qc,
  },

  actions: {
    async fetch(force = false) {
      if (this.fetched && !force) return

      this.loading = true
      this.error   = null
      try {
        const { data } = await axios.get('/api/master-data')

        // Map semua key ke state
        const keys = [
          'ket_bayar', 'ruangan', 'kelas', 'bangsal', 'keterangan_batal',
          'status_ok', 'ket_up_selling', 'status_ket_qc', 'note_kamar',
          'cara_masuk', 'diagnosa', 'jaminan',
        ]
        keys.forEach(k => {
          if (Array.isArray(data[k])) this[k] = data[k]
        })

        this.fetched = true
      } catch (err) {
        this.error = err.response?.data?.message ?? 'Gagal memuat master data'
        console.warn('[useMasterDataStore] fetch error:', err.message)
        // Fallback defaults agar form tetap bisa digunakan
        this._setDefaults()
      } finally {
        this.loading = false
      }
    },

    _setDefaults() {
      if (!this.keterangan_batal.length) this.keterangan_batal = [
        'APS Alih RS Lain', 'APS Rawat Jalan', 'Saran Alih RS Lain', 'Saran Konsul Poli',
        'Sisrute Tidak Dapat Kamar', 'Batal Rawat', 'Kamar Penuh',
        'Pasien Menolak', 'DPJP Tidak Setuju', 'Keluarga Menolak', 'Kondisi Membaik',
      ]
      if (!this.ruangan.length) this.ruangan = [
        'IGD Umum', 'IGD Bedah', 'IGD Anak', 'IGD Kebidanan',
        'Ruang Mawar', 'Ruang Anggrek', 'Ruang Dahlia', 'Ruang Flamboyan',
        'ICU', 'NICU', 'HCU', 'PICU',
      ]
      if (!this.status_ok.length) this.status_ok = ['Bedah', 'Non Bedah']
      if (!this.ket_up_selling.length) this.ket_up_selling = ['Naik Kelas', 'Perubahan Jaminan']
      if (!this.note_kamar.length) this.note_kamar = [
        'Kelas 1 Bedah Laki-laki', 'Kelas 2 Bedah Laki-laki', 'Kelas 3 Bedah Laki-laki',
        'Kelas 1 Bedah Perempuan', 'Kelas 2 Bedah Perempuan', 'Kelas 3 Bedah Perempuan',
        'Kelas 1 Internis Laki-laki', 'Kelas 2 Internis Laki-laki', 'Kelas 3 Internis Laki-laki',
        'Kelas 1 Internis Perempuan', 'Kelas 2 Internis Perempuan', 'Kelas 3 Internis Perempuan',
        'Kelas 1 Onkologi Laki-laki', 'Kelas 2 Onkologi Laki-laki', 'Kelas 3 Onkologi Laki-laki',
        'Kelas 1 Onkologi Perempuan', 'Kelas 2 Onkologi Perempuan', 'Kelas 3 Onkologi Perempuan',
        'Kelas 1 Kebidanan', 'Kelas 2 Kebidanan', 'Kelas 3 Kebidanan',
        'Kelas 1 Anak', 'Kelas 2 Anak', 'Kelas 3 Anak', 'Kelas VIP',
      ]
      if (!this.jaminan.length) this.jaminan = ['BPJS', 'Umum', 'Asuransi', 'Jasa Raharja', 'BPJS Ketenagakerjaan', 'Gratis', 'Lainnya']
      if (!this.kelas.length) this.kelas = ['Kelas VIP', 'Kelas 1', 'Kelas 2', 'Kelas 3', 'Suite Room', 'VVIP']
      if (!this.diagnosa.length) this.diagnosa = [
        'Hipertensi', 'Diabetes Mellitus', 'Stroke', 'Gagal Jantung', 'ISPA',
        'Pneumonia', 'Appendisitis', 'Fraktur', 'Demam Berdarah', 'Typhoid',
        'Gastroenteritis', 'Anemia', 'Asma', 'Epilepsi', 'Lainnya',
      ]
      if (!this.cara_masuk.length) this.cara_masuk = ['IGD', 'Poli', 'Rujukan', 'Langsung']
      if (!this.status_ket_qc.length) this.status_ket_qc = ['Belum Dapat Kamar', 'Antri Kamar', 'Sudah Dapat Kamar']
    },

    /** Force refresh — panggil setelah admin update master data */
    async refresh() {
      this.fetched = false
      await this.fetch(true)
    },
  },
})
