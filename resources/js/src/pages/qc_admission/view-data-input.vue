<script setup>
// View Data Input — Summary/History per pasien dari semua modul

const activeTab = ref('summary')

const tabs = [
  { key: 'summary',          label: 'Summary Pasien',   icon: 'ri-user-heart-line',       color: 'primary' },
  { key: 'quality-control',  label: 'Quality Control',  icon: 'ri-shield-check-line',     color: 'primary' },
  { key: 'batal-ranap',      label: 'Batal Ranap',      icon: 'ri-close-circle-line',     color: 'error' },
  { key: 'edukasi-lanjutan', label: 'Edukasi Lanjutan', icon: 'ri-book-open-line',        color: 'warning' },
  { key: 'up-selling',       label: 'Up Selling',       icon: 'ri-arrow-up-circle-line',  color: 'success' },
]

const search    = ref('')
const dateFrom  = ref('')
const dateTo    = ref('')
const noMrFilter = ref('')

// ── Mock data ─────────────────────────────────────────────────────────────────
const qcData = ref([
  { id: 1, tanggal: '29/06/2026, 19.41.58', no_mr: '813500', no_reg: 'REG001', nama_pasien: 'ELLY MAYA, NY',     jaminan: 'BPJS', status: 'Edukasi',          petugas: 'Nurul',  durasi_tunggu: '10:17:19', edukasi_kamar: '',            note: 'Pasien mengerti', keluarga_pasien: 'Bambang' },
  { id: 2, tanggal: '29/06/2026, 18.05.22', no_mr: '575360', no_reg: 'REG002', nama_pasien: 'IDH SUBINGSEN, NY', jaminan: 'BPJS', status: 'Edukasi lanjutan', petugas: 'Reskim', durasi_tunggu: '01:32:10', edukasi_kamar: 'Ruang Mawar', note: 'Keluarga hadir',  keluarga_pasien: 'Siti'    },
  { id: 3, tanggal: '29/06/2026, 17.44.00', no_mr: '087220', no_reg: 'REG003', nama_pasien: 'RUSMINI, NY',       jaminan: 'Umum', status: 'Masuk',             petugas: 'Nurul',  durasi_tunggu: '00:48:22', edukasi_kamar: 'Ruang Anggrek', note: 'Dirujuk',      keluarga_pasien: 'Andi'    },
  { id: 4, tanggal: '28/06/2026, 14.10.00', no_mr: '816302', no_reg: 'REG004', nama_pasien: 'PUSPA SARI, AN',    jaminan: 'BPJS', status: 'Edukasi lanjutan', petugas: 'AYU Putri Anisa', durasi_tunggu: '03:20:10', edukasi_kamar: 'ICU', note: '', keluarga_pasien: 'Rini' },
])

const batalData = ref([
  { id: 1, tanggal: '29/06/2026, 19.43.45', no_reg: 'REG001BR', nama_pasien: 'ELLY MAYA, NY',     keterangan_batal: 'Kamar Penuh', status_ok: 'Pending', diagnosa: 'Hipertensi', petugas: 'Nurul' },
  { id: 2, tanggal: '28/06/2026, 10.20.00', no_reg: 'REG003BR', nama_pasien: 'RUSMINI, NY',       keterangan_batal: 'Pasien Menolak', status_ok: 'OK',    diagnosa: 'Diabetes',   petugas: 'Reskim' },
])

const edukasiData = ref([
  { id: 1, tanggal: '29/06/2026', no_mr: '813500', nama_pasien: 'ELLY MAYA, NY',     bulan: 'JUNI', edukasi_kamar: 'Ruang Mawar',   petugas: 'Nurul',  status: 'Menunggu', keluarga_pasien: 'Bambang' },
  { id: 2, tanggal: '29/06/2026', no_mr: '575360', nama_pasien: 'IDH SUBINGSEN, NY', bulan: 'JUNI', edukasi_kamar: 'Ruang Anggrek', petugas: 'Reskim', status: 'Selesai',  keluarga_pasien: 'Siti'    },
  { id: 3, tanggal: '28/06/2026', no_mr: '087220', nama_pasien: 'RUSMINI, NY',       bulan: 'JUNI', edukasi_kamar: '',              petugas: 'AYU Putri Anisa', status: 'Menunggu', keluarga_pasien: '' },
])

const upSellingData = ref([
  { id: 1, tanggal: '29/06/2026, 08.00.00', no_reg: 'REG001UP', nama_pasien: 'ELLY MAYA, NY',     rekomendasi_kelas: 'Kelas 1', kelas_diambil: 'Kelas 2', status: 'Tidak Berhasil', petugas: 'Nurul',  jaminan: 'BPJS' },
  { id: 2, tanggal: '28/06/2026, 09.30.00', no_reg: 'REG004UP', nama_pasien: 'PUSPA SARI, AN',    rekomendasi_kelas: 'VIP',     kelas_diambil: 'Kelas 1', status: 'Berhasil',       petugas: 'Reskim', jaminan: 'BPJS' },
])

// ── Summary — aggregate per No. MR ───────────────────────────────────────────
const summaryData = computed(() => {
  const map = {}

  qcData.value.forEach(r => {
    if (!map[r.no_mr]) map[r.no_mr] = {
      no_mr: r.no_mr, nama_pasien: r.nama_pasien, jaminan: r.jaminan,
      qc_count: 0, edukasi_count: 0, batal_count: 0, up_count: 0,
      last_status: '', last_tanggal: '',
    }
    map[r.no_mr].qc_count++
    map[r.no_mr].last_status = r.status
    map[r.no_mr].last_tanggal = r.tanggal
  })

  edukasiData.value.forEach(r => {
    if (!map[r.no_mr]) map[r.no_mr] = {
      no_mr: r.no_mr, nama_pasien: r.nama_pasien, jaminan: '—',
      qc_count: 0, edukasi_count: 0, batal_count: 0, up_count: 0,
      last_status: '', last_tanggal: r.tanggal,
    }
    map[r.no_mr].edukasi_count++
  })

  return Object.values(map)
})

// ── Headers ───────────────────────────────────────────────────────────────────
const summaryHeaders = [
  { title: 'No. MR',        key: 'no_mr',         sortable: true },
  { title: 'Nama Pasien',   key: 'nama_pasien',   sortable: true },
  { title: 'Jaminan',       key: 'jaminan',       sortable: true },
  { title: 'QC',            key: 'qc_count',      sortable: true, align: 'center' },
  { title: 'Edukasi',       key: 'edukasi_count', sortable: true, align: 'center' },
  { title: 'Batal Ranap',   key: 'batal_count',   sortable: true, align: 'center' },
  { title: 'Up Selling',    key: 'up_count',      sortable: true, align: 'center' },
  { title: 'Status Terakhir', key: 'last_status', sortable: true },
  { title: 'Tanggal',       key: 'last_tanggal',  sortable: true },
]

const qcHeaders = [
  { title: 'Tanggal',       key: 'tanggal',       sortable: true },
  { title: 'No. MR',        key: 'no_mr',         sortable: true },
  { title: 'Nama Pasien',   key: 'nama_pasien',   sortable: true },
  { title: 'Jaminan',       key: 'jaminan',       sortable: true },
  { title: 'Status',        key: 'status',        sortable: true, align: 'center' },
  { title: 'Petugas',       key: 'petugas',       sortable: true },
  { title: 'Durasi',        key: 'durasi_tunggu', sortable: true, align: 'center' },
  { title: 'Edukasi Kamar', key: 'edukasi_kamar', sortable: false },
  { title: 'Keluarga',      key: 'keluarga_pasien', sortable: false },
]

const batalHeaders = [
  { title: 'Tanggal',           key: 'tanggal',           sortable: true },
  { title: 'No. Reg',           key: 'no_reg',            sortable: true },
  { title: 'Nama Pasien',       key: 'nama_pasien',       sortable: true },
  { title: 'Keterangan Batal',  key: 'keterangan_batal',  sortable: true },
  { title: 'Status OK',         key: 'status_ok',         sortable: true, align: 'center' },
  { title: 'Diagnosa',          key: 'diagnosa',          sortable: true },
  { title: 'Petugas',           key: 'petugas',           sortable: true },
]

const edukasiHeaders = [
  { title: 'Tanggal',       key: 'tanggal',       sortable: true },
  { title: 'No. MR',        key: 'no_mr',         sortable: true },
  { title: 'Nama Pasien',   key: 'nama_pasien',   sortable: true },
  { title: 'Bulan',         key: 'bulan',         sortable: true },
  { title: 'Edukasi Kamar', key: 'edukasi_kamar', sortable: false },
  { title: 'Status',        key: 'status',        sortable: true, align: 'center' },
  { title: 'Keluarga',      key: 'keluarga_pasien', sortable: false },
  { title: 'Petugas',       key: 'petugas',       sortable: true },
]

const upSellingHeaders = [
  { title: 'Tanggal',         key: 'tanggal',           sortable: true },
  { title: 'No. Reg',         key: 'no_reg',            sortable: true },
  { title: 'Nama Pasien',     key: 'nama_pasien',       sortable: true },
  { title: 'Rek. Kelas',      key: 'rekomendasi_kelas', sortable: true, align: 'center' },
  { title: 'Kelas Diambil',   key: 'kelas_diambil',     sortable: true, align: 'center' },
  { title: 'Status',          key: 'status',            sortable: true, align: 'center' },
  { title: 'Petugas',         key: 'petugas',           sortable: true },
]

const activeHeaders = computed(() => ({
  summary:           summaryHeaders,
  'quality-control': qcHeaders,
  'batal-ranap':     batalHeaders,
  'edukasi-lanjutan': edukasiHeaders,
  'up-selling':      upSellingHeaders,
})[activeTab.value] ?? [])

const activeData = computed(() => ({
  summary:            summaryData.value,
  'quality-control':  qcData.value,
  'batal-ranap':      batalData.value,
  'edukasi-lanjutan': edukasiData.value,
  'up-selling':       upSellingData.value,
})[activeTab.value] ?? [])

const filteredData = computed(() => {
  let data = activeData.value
  const q = search.value.toLowerCase().trim()
  if (q) {
    data = data.filter(r =>
      Object.values(r).some(v => String(v).toLowerCase().includes(q))
    )
  }
  if (noMrFilter.value.trim()) {
    const mr = noMrFilter.value.trim()
    data = data.filter(r =>
      r.no_mr?.includes(mr) || r.no_reg?.includes(mr)
    )
  }
  return data
})

function statusColor(s) {
  return ({
    'Edukasi': 'success', 'Edukasi lanjutan': 'warning', 'Masuk': 'info',
    'OK': 'success', 'Pending': 'warning', 'Ditolak': 'error',
    'Berhasil': 'success', 'Tidak Berhasil': 'error',
    'Selesai': 'success', 'Menunggu': 'warning',
  })[s] ?? 'secondary'
}

function exportCSV() {
  const headers = activeHeaders.value.map(h => h.title)
  const rows    = filteredData.value.map(row =>
    activeHeaders.value.map(h => `"${row[h.key] ?? ''}"`)
  )
  const csv = [headers.join(','), ...rows.map(r => r.join(','))].join('\n')
  const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' })
  const url  = URL.createObjectURL(blob)
  const a    = document.createElement('a')
  a.href     = url
  a.download = `view-data-${activeTab.value}-${new Date().toISOString().slice(0,10)}.csv`
  a.click()
  URL.revokeObjectURL(url)
}
</script>

<template>
  <div>
    <!-- Page header -->
    <div class="d-flex align-center justify-space-between flex-wrap gap-3 mb-5">
      <div>
        <h4 class="page-title">View Data Input</h4>
        <p class="text-body-2 text-medium-emphasis mb-0">Summary & history semua data input per modul</p>
      </div>
      <VBtn
        variant="tonal"
        color="success"
        prepend-icon="ri-file-download-line"
        size="small"
        @click="exportCSV"
      >
        Export CSV
      </VBtn>
    </div>

    <!-- Module tabs -->
    <VCard elevation="0" border rounded="lg" class="mb-4">
      <VTabs v-model="activeTab" color="primary" show-arrows>
        <VTab v-for="tab in tabs" :key="tab.key" :value="tab.key">
          <VIcon :icon="tab.icon" size="16" class="me-2" />
          {{ tab.label }}
        </VTab>
      </VTabs>
    </VCard>

    <!-- Filter bar -->
    <VCard elevation="0" border rounded="lg" class="mb-4">
      <VCardText class="py-3">
        <VRow align="center" dense>
          <VCol cols="12" sm="5">
            <VTextField
              v-model="search"
              placeholder="Cari nama, nomor, petugas..."
              prepend-inner-icon="ri-search-line"
              variant="outlined"
              density="compact"
              hide-details
              clearable
            />
          </VCol>
          <VCol v-if="activeTab !== 'batal-ranap' && activeTab !== 'up-selling'" cols="12" sm="3">
            <VTextField
              v-model="noMrFilter"
              placeholder="Filter No. MR..."
              prepend-inner-icon="ri-id-card-line"
              variant="outlined"
              density="compact"
              hide-details
              clearable
            />
          </VCol>
          <VCol cols="12" sm="2">
            <VTextField
              v-model="dateFrom"
              label="Dari"
              type="date"
              variant="outlined"
              density="compact"
              hide-details
            />
          </VCol>
          <VCol cols="12" sm="2">
            <VTextField
              v-model="dateTo"
              label="Sampai"
              type="date"
              variant="outlined"
              density="compact"
              hide-details
            />
          </VCol>
          <VCol class="d-flex justify-end" cols="auto">
            <VBtn
              icon
              variant="text"
              size="small"
              color="secondary"
              title="Reset filter"
              @click="search = ''; dateFrom = ''; dateTo = ''; noMrFilter = ''"
            >
              <VIcon icon="ri-refresh-line" />
            </VBtn>
          </VCol>
        </VRow>
      </VCardText>
    </VCard>

    <!-- Result count -->
    <div class="d-flex align-center gap-2 mb-3">
      <VChip size="small" color="primary" variant="tonal">
        {{ filteredData.length }} data
      </VChip>
      <span class="text-caption text-medium-emphasis">
        {{ activeTab === 'summary' ? 'pasien terdaftar' : 'record ditemukan' }}
      </span>
    </div>

    <!-- Data table -->
    <VCard elevation="0" border rounded="lg">
      <VDataTable
        :headers="activeHeaders"
        :items="filteredData"
        density="compact"
        hover
        :items-per-page="15"
        class="view-table"
      >
        <!-- Summary special columns -->
        <template v-if="activeTab === 'summary'" #item.qc_count="{ item }">
          <VChip size="x-small" color="primary" variant="tonal">{{ item.qc_count }}</VChip>
        </template>
        <template v-if="activeTab === 'summary'" #item.edukasi_count="{ item }">
          <VChip size="x-small" color="warning" variant="tonal">{{ item.edukasi_count }}</VChip>
        </template>
        <template v-if="activeTab === 'summary'" #item.batal_count="{ item }">
          <VChip size="x-small" color="error" variant="tonal">{{ item.batal_count }}</VChip>
        </template>
        <template v-if="activeTab === 'summary'" #item.up_count="{ item }">
          <VChip size="x-small" color="success" variant="tonal">{{ item.up_count }}</VChip>
        </template>

        <!-- Status chips (all tabs) -->
        <template #item.status="{ item }">
          <VChip :color="statusColor(item.status)" size="small" variant="tonal" label>
            {{ item.status }}
          </VChip>
        </template>
        <template #item.last_status="{ item }">
          <VChip v-if="item.last_status" :color="statusColor(item.last_status)" size="small" variant="tonal" label>
            {{ item.last_status }}
          </VChip>
          <span v-else class="text-medium-emphasis">—</span>
        </template>
        <template #item.status_ok="{ item }">
          <VChip :color="statusColor(item.status_ok)" size="small" variant="tonal" label>
            {{ item.status_ok || 'Pending' }}
          </VChip>
        </template>

        <!-- Empty state -->
        <template #no-data>
          <div class="text-center py-10 text-medium-emphasis">
            <VIcon icon="ri-database-2-line" size="40" class="mb-3 opacity-40" />
            <p class="text-body-1 font-weight-medium mb-1">Tidak ada data</p>
            <p class="text-body-2">Coba ubah filter atau pilih modul yang berbeda</p>
          </div>
        </template>
      </VDataTable>
    </VCard>
  </div>
</template>

<style scoped>
.page-title { font-size: 1.1rem; font-weight: 700; margin-bottom: 2px; }
.view-table :deep(.v-data-table__thead th) {
  font-size: 0.7rem !important;
  text-transform: uppercase;
  letter-spacing: 0.04em;
  font-weight: 700 !important;
  white-space: nowrap;
}
</style>
