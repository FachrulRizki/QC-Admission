<script setup>
import axios from 'axios'

const activeTab  = ref('summary')
const loading    = ref(false)
const search     = ref('')
const dateFrom   = ref('')
const dateTo     = ref('')
const noMrFilter = ref('')

const tabs = [
  { key: 'summary',           label: 'Summary Pasien',   icon: 'ri-user-heart-line',       color: 'primary' },
  { key: 'quality-control',   label: 'Quality Control',  icon: 'ri-shield-check-line',     color: 'primary' },
  { key: 'batal-ranap',       label: 'Batal Ranap',      icon: 'ri-close-circle-line',     color: 'error' },
  { key: 'edukasi-lanjutan',  label: 'Edukasi Lanjutan', icon: 'ri-book-open-line',        color: 'warning' },
  { key: 'up-selling',        label: 'Up Selling',       icon: 'ri-arrow-up-circle-line',  color: 'success' },
]

// ── Data dari API ─────────────────────────────────────────────────────────────
const qcData        = ref([])
const batalData     = ref([])
const edukasiData   = ref([])
const upSellingData = ref([])

async function loadAll() {
  loading.value = true
  try {
    const [qcRes, batalRes, eduRes, upRes] = await Promise.all([
      axios.get('/api/quality-control',   { params: { per_page: 500 } }),
      axios.get('/api/batal-ranap',       { params: { per_page: 500 } }),
      axios.get('/api/edukasi-lanjutan',  { params: { per_page: 500 } }),
      axios.get('/api/up-selling',        { params: { per_page: 500 } }),
    ])
    qcData.value        = qcRes.data.data       ?? []
    batalData.value     = batalRes.data.data    ?? []
    edukasiData.value   = eduRes.data.data      ?? []
    upSellingData.value = upRes.data.data       ?? []
  } catch (e) {
    console.error('View data input load error', e)
  } finally {
    loading.value = false
  }
}

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
    map[r.no_mr].last_status  = r.status
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

  batalData.value.forEach(r => {
    const key = r.no_mr ?? r.no_reg
    if (!map[key]) map[key] = {
      no_mr: key, nama_pasien: r.nama_pasien, jaminan: '—',
      qc_count: 0, edukasi_count: 0, batal_count: 0, up_count: 0,
      last_status: '', last_tanggal: r.tanggal,
    }
    map[key].batal_count++
  })

  upSellingData.value.forEach(r => {
    const key = r.no_mr ?? r.no_reg
    if (!map[key]) map[key] = {
      no_mr: key, nama_pasien: r.nama_pasien, jaminan: r.jaminan ?? '—',
      qc_count: 0, edukasi_count: 0, batal_count: 0, up_count: 0,
      last_status: '', last_tanggal: r.tanggal,
    }
    map[key].up_count++
  })

  return Object.values(map)
})

// ── Headers ───────────────────────────────────────────────────────────────────
const summaryHeaders = [
  { title: 'No. MR',          key: 'no_mr',         sortable: true },
  { title: 'Nama Pasien',     key: 'nama_pasien',   sortable: true },
  { title: 'Jaminan',         key: 'jaminan',       sortable: true },
  { title: 'QC',              key: 'qc_count',      sortable: true, align: 'center' },
  { title: 'Edukasi',         key: 'edukasi_count', sortable: true, align: 'center' },
  { title: 'Batal Ranap',     key: 'batal_count',   sortable: true, align: 'center' },
  { title: 'Up Selling',      key: 'up_count',      sortable: true, align: 'center' },
  { title: 'Status Terakhir', key: 'last_status',   sortable: true },
  { title: 'Tanggal',         key: 'last_tanggal',  sortable: true },
]
const qcHeaders = [
  { title: 'Tanggal',       key: 'tanggal',         sortable: true },
  { title: 'No. MR',        key: 'no_mr',           sortable: true },
  { title: 'No. Reg',       key: 'no_reg',          sortable: true },
  { title: 'Nama Pasien',   key: 'nama_pasien',     sortable: true },
  { title: 'Jaminan',       key: 'jaminan',         sortable: true },
  { title: 'Status',        key: 'status',          sortable: true, align: 'center' },
  { title: 'Petugas',       key: 'petugas',         sortable: true },
  { title: 'Durasi',        key: 'durasi_tunggu',   sortable: true, align: 'center' },
  { title: 'Edukasi Kamar', key: 'edukasi_kamar',   sortable: false },
  { title: 'Keluarga',      key: 'keluarga_pasien', sortable: false },
]
const batalHeaders = [
  { title: 'Tanggal',          key: 'tanggal',          sortable: true },
  { title: 'No. Reg',          key: 'no_reg',           sortable: true },
  { title: 'Nama Pasien',      key: 'nama_pasien',      sortable: true },
  { title: 'Keterangan Batal', key: 'keterangan_batal', sortable: true },
  { title: 'Status OK',        key: 'status_ok',        sortable: true, align: 'center' },
  { title: 'Diagnosa',         key: 'diagnosa',         sortable: true },
  { title: 'Petugas',          key: 'petugas',          sortable: true },
]
const edukasiHeaders = [
  { title: 'Tanggal',       key: 'tanggal',         sortable: true },
  { title: 'No. MR',        key: 'no_mr',           sortable: true },
  { title: 'Nama Pasien',   key: 'nama_pasien',     sortable: true },
  { title: 'Bulan',         key: 'bulan',           sortable: true },
  { title: 'Edukasi Kamar', key: 'edukasi_kamar',   sortable: false },
  { title: 'Status',        key: 'status',          sortable: true, align: 'center' },
  { title: 'Keluarga',      key: 'keluarga_pasien', sortable: false },
  { title: 'Petugas',       key: 'petugas',         sortable: true },
]
const upSellingHeaders = [
  { title: 'Tanggal',       key: 'tanggal',           sortable: true },
  { title: 'No. Reg',       key: 'no_reg',            sortable: true },
  { title: 'Nama Pasien',   key: 'nama_pasien',       sortable: true },
  { title: 'Rek. Kelas',    key: 'rekomendasi_kelas', sortable: true, align: 'center' },
  { title: 'Kelas Diambil', key: 'kelas_diambil',     sortable: true, align: 'center' },
  { title: 'Status',        key: 'status',            sortable: true, align: 'center' },
  { title: 'Petugas',       key: 'petugas',           sortable: true },
]

const activeHeaders = computed(() => ({
  summary:            summaryHeaders,
  'quality-control':  qcHeaders,
  'batal-ranap':      batalHeaders,
  'edukasi-lanjutan': edukasiHeaders,
  'up-selling':       upSellingHeaders,
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
  const q  = search.value.toLowerCase().trim()
  if (q) data = data.filter(r => Object.values(r).some(v => String(v ?? '').toLowerCase().includes(q)))
  if (noMrFilter.value.trim()) {
    const mr = noMrFilter.value.trim()
    data = data.filter(r => r.no_mr?.includes(mr) || r.no_reg?.includes(mr))
  }
  if (dateFrom.value) data = data.filter(r => r.tanggal >= dateFrom.value)
  if (dateTo.value)   data = data.filter(r => r.tanggal <= dateTo.value)
  return data
})

// ── Grand stats ───────────────────────────────────────────────────────────────
const grandStats = computed(() => ({
  qc:      qcData.value.length,
  batal:   batalData.value.length,
  edukasi: edukasiData.value.length,
  up:      upSellingData.value.length,
}))

function statusColor(s) {
  return ({
    'Edukasi': 'success', 'Edukasi lanjutan': 'warning', 'Masuk': 'info',
    'OK': 'success', 'Bedah': 'success', 'Non Bedah': 'info',
    'Pending': 'warning', 'Ditolak': 'error',
    'Berhasil': 'success', 'Tidak Berhasil': 'error',
    'Selesai': 'success', 'Menunggu': 'warning',
  })[s] ?? 'secondary'
}

function exportCSV() {
  const headers = activeHeaders.value.map(h => h.title)
  const rows    = filteredData.value.map(row =>
    activeHeaders.value.map(h => `"${row[h.key] ?? ''}"`)
  )
  const csv  = [headers.join(','), ...rows.map(r => r.join(','))].join('\n')
  const blob = new Blob(['\uFEFF' + csv], { type: 'text/csv;charset=utf-8;' })
  const url  = URL.createObjectURL(blob)
  const a    = document.createElement('a')
  a.href     = url
  a.download = `view-data-${activeTab.value}-${new Date().toISOString().slice(0, 10)}.csv`
  a.click()
  URL.revokeObjectURL(url)
}

onMounted(() => loadAll())
</script>

<template>
  <div>
    <!-- Page header -->
    <div class="page-hero page-hero--view mb-5">
      <div class="page-hero__content">
        <div class="page-hero__badge">
          <VIcon icon="ri-table-line" size="13" />
          QC Admission · View Data Input
        </div>
        <h1 class="page-hero__title">View Data Input</h1>
        <p class="page-hero__subtitle">Summary & history semua data input per modul · Data real-time dari database</p>
      </div>
      <div class="d-flex gap-2 align-center" style="position:relative;z-index:2">
        <VBtn icon variant="text" color="white" size="small" :loading="loading" title="Refresh" @click="loadAll">
          <VIcon icon="ri-refresh-line" />
        </VBtn>
        <VBtn color="white" variant="elevated" rounded="lg" size="small"
          prepend-icon="ri-file-download-line" style="color:#4facfe"
          @click="exportCSV">
          Export CSV
        </VBtn>
      </div>
      <VIcon icon="ri-table-line" class="page-hero__icon" />
    </div>

    <!-- Grand stats -->
    <VRow dense class="mb-4">
      <VCol cols="6" sm="3">
        <VCard
          elevation="0" border rounded="lg" class="pa-3 text-center cursor-pointer stat-tab"
          :class="activeTab === 'quality-control' ? 'stat-tab-active' : ''"
          @click="activeTab = 'quality-control'"
        >
          <VIcon icon="ri-shield-check-line" size="20" color="primary" class="mb-1" />
          <p class="text-h5 font-weight-bold text-primary mb-0">{{ grandStats.qc }}</p>
          <p class="text-caption text-medium-emphasis mb-0">Quality Control</p>
        </VCard>
      </VCol>
      <VCol cols="6" sm="3">
        <VCard
          elevation="0" border rounded="lg" class="pa-3 text-center cursor-pointer stat-tab"
          :class="activeTab === 'batal-ranap' ? 'stat-tab-active' : ''"
          @click="activeTab = 'batal-ranap'"
        >
          <VIcon icon="ri-close-circle-line" size="20" color="error" class="mb-1" />
          <p class="text-h5 font-weight-bold mb-0" style="color:rgb(var(--v-theme-error))">{{ grandStats.batal }}</p>
          <p class="text-caption text-medium-emphasis mb-0">Batal Ranap</p>
        </VCard>
      </VCol>
      <VCol cols="6" sm="3">
        <VCard
          elevation="0" border rounded="lg" class="pa-3 text-center cursor-pointer stat-tab"
          :class="activeTab === 'edukasi-lanjutan' ? 'stat-tab-active' : ''"
          @click="activeTab = 'edukasi-lanjutan'"
        >
          <VIcon icon="ri-book-open-line" size="20" color="warning" class="mb-1" />
          <p class="text-h5 font-weight-bold mb-0" style="color:rgb(var(--v-theme-warning))">{{ grandStats.edukasi }}</p>
          <p class="text-caption text-medium-emphasis mb-0">Edukasi Lanjutan</p>
        </VCard>
      </VCol>
      <VCol cols="6" sm="3">
        <VCard
          elevation="0" border rounded="lg" class="pa-3 text-center cursor-pointer stat-tab"
          :class="activeTab === 'up-selling' ? 'stat-tab-active' : ''"
          @click="activeTab = 'up-selling'"
        >
          <VIcon icon="ri-arrow-up-circle-line" size="20" color="success" class="mb-1" />
          <p class="text-h5 font-weight-bold mb-0" style="color:rgb(var(--v-theme-success))">{{ grandStats.up }}</p>
          <p class="text-caption text-medium-emphasis mb-0">Up Selling</p>
        </VCard>
      </VCol>
    </VRow>

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
          <VCol cols="12" sm="4">
            <VTextField
              v-model="search"
              placeholder="Cari nama, nomor, petugas..."
              prepend-inner-icon="ri-search-line"
              variant="outlined" density="compact" hide-details clearable
            />
          </VCol>
          <VCol v-if="activeTab !== 'batal-ranap' && activeTab !== 'up-selling'" cols="12" sm="3">
            <VTextField
              v-model="noMrFilter"
              placeholder="Filter No. MR / No. Reg..."
              prepend-inner-icon="ri-id-card-line"
              variant="outlined" density="compact" hide-details clearable
            />
          </VCol>
          <VCol cols="6" sm="2">
            <VTextField v-model="dateFrom" label="Dari" type="date" variant="outlined" density="compact" hide-details />
          </VCol>
          <VCol cols="6" sm="2">
            <VTextField v-model="dateTo" label="Sampai" type="date" variant="outlined" density="compact" hide-details />
          </VCol>
          <VCol class="d-flex justify-end" cols="auto">
            <VBtn icon variant="text" size="small" color="secondary" title="Reset filter"
              @click="search = ''; dateFrom = ''; dateTo = ''; noMrFilter = ''">
              <VIcon icon="ri-refresh-line" />
            </VBtn>
          </VCol>
        </VRow>
      </VCardText>
    </VCard>

    <!-- Result count -->
    <div class="d-flex align-center gap-2 mb-3">
      <VChip size="small" color="primary" variant="tonal">{{ filteredData.length }} data</VChip>
      <span class="text-caption text-medium-emphasis">
        {{ activeTab === 'summary' ? 'pasien terdaftar' : 'record ditemukan' }}
      </span>
      <VProgressCircular v-if="loading" size="16" width="2" indeterminate color="primary" class="ms-1" />
    </div>

    <!-- Data table -->
    <VCard elevation="0" border rounded="lg">
      <VDataTable
        :headers="activeHeaders"
        :items="filteredData"
        density="compact"
        hover
        :loading="loading"
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
.stat-tab { transition: box-shadow 0.2s, transform 0.15s; }
.stat-tab:hover { box-shadow: 0 4px 16px rgba(var(--v-shadow-key-umbra-color), 0.1) !important; transform: translateY(-1px); }
.stat-tab-active { border-color: rgb(var(--v-theme-primary)) !important; }
.view-table :deep(.v-data-table__thead th) {
  font-size: 0.7rem !important;
  text-transform: uppercase;
  letter-spacing: 0.04em;
  font-weight: 700 !important;
  white-space: nowrap;
}
</style>
