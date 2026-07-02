<script setup>
import axios from 'axios'
import { useAuthStore } from '@/stores/useAuthStore'
import SummaryCards from '@/components/SummaryCards.vue'

const auth    = useAuthStore()
const loading = ref(false)
const search  = ref('')
const dateFrom = ref('')
const dateTo   = ref('')
const activeTab = ref('batal-ranap')

const isKasir = computed(() => auth.isKasir)

const allTabs = [
  { key: 'summary',          label: 'Summary',          icon: 'ri-user-heart-line',      roles: ['admin','qc_admission'] },
  { key: 'quality-control',  label: 'Quality Control',  icon: 'ri-shield-check-line',    roles: ['admin','qc_admission'] },
  { key: 'batal-ranap',      label: 'Batal Ranap',      icon: 'ri-close-circle-line',    roles: ['admin','qc_admission','kasir'] },
  { key: 'edukasi-lanjutan', label: 'Edukasi Lanjutan', icon: 'ri-book-open-line',       roles: ['admin','qc_admission'] },
  { key: 'up-selling',       label: 'Up Selling',       icon: 'ri-arrow-up-circle-line', roles: ['admin','qc_admission'] },
]

const tabs = computed(() => allTabs.filter(t => t.roles.includes(auth.userRole ?? 'kasir')))

const qcData       = ref([])
const batalData    = ref([])
const edukasiData  = ref([])
const upData       = ref([])

async function safeGet(url, params = {}) {
  try {
    const { data } = await axios.get(url, { params })
    return data.data ?? []
  } catch { return [] }
}

async function loadAll() {
  loading.value = true
  try {
    if (isKasir.value) {
      batalData.value = await safeGet('/api/batal-ranap', { per_page: 500 })
    } else {
      const [qc, batal, edu, up] = await Promise.all([
        safeGet('/api/quality-control',  { per_page: 500 }),
        safeGet('/api/batal-ranap',      { per_page: 500 }),
        safeGet('/api/edukasi-lanjutan', { per_page: 500 }),
        safeGet('/api/up-selling',       { per_page: 500 }),
      ])
      qcData.value = qc; batalData.value = batal; edukasiData.value = edu; upData.value = up
    }
  } finally { loading.value = false }
}

const grandStats = computed(() => ({
  qc: qcData.value.length, batal: batalData.value.length,
  edukasi: edukasiData.value.length, up: upData.value.length,
}))

const summaryData = computed(() => {
  const map = {}
  const merge = (list, countKey, tglKey='tanggal') => list.forEach(r => {
    const k = r.no_mr ?? r.no_reg ?? 'x'
    if (!map[k]) map[k] = { no_mr: k, nama_pasien: r.nama_pasien, jaminan: r.jaminan||'—', qc:0, edukasi:0, batal:0, up:0, tanggal: r[tglKey] }
    map[k][countKey]++
    if (r[tglKey] > (map[k].tanggal||'')) map[k].tanggal = r[tglKey]
  })
  merge(qcData.value, 'qc'); merge(edukasiData.value, 'edukasi')
  merge(batalData.value, 'batal'); merge(upData.value, 'up')
  return Object.values(map)
})

const activeData = computed(() => {
  const map = { summary: summaryData.value, 'quality-control': qcData.value, 'batal-ranap': batalData.value, 'edukasi-lanjutan': edukasiData.value, 'up-selling': upData.value }
  return map[activeTab.value] ?? []
})

const filteredData = computed(() => {
  let d = activeData.value
  if (search.value.trim()) {
    const q = search.value.toLowerCase()
    d = d.filter(r => Object.values(r).some(v => String(v??'').toLowerCase().includes(q)))
  }
  if (dateFrom.value) d = d.filter(r => (r.tanggal||r.tgl_daftar||'') >= dateFrom.value)
  if (dateTo.value)   d = d.filter(r => (r.tanggal||r.tgl_daftar||'') <= dateTo.value)
  return d
})

function statusColor(s) {
  return ({'Edukasi':'success','Edukasi lanjutan':'warning','Bedah':'success','Non Bedah':'info','Selesai':'success','Menunggu':'warning','Berhasil':'success','Tidak Berhasil':'error','Pending':'warning'})[s] ?? 'secondary'
}

function exportCSV() {
  const cols = activeHeaders.value
  const csv  = [cols.map(h=>h.title).join(','), ...filteredData.value.map(r => cols.map(h => `"${r[h.key]??''}"`).join(','))].join('\n')
  const a = document.createElement('a'); a.href = URL.createObjectURL(new Blob(['\uFEFF'+csv],{type:'text/csv'}))
  a.download = `view-${activeTab.value}-${new Date().toISOString().slice(0,10)}.csv`; a.click()
}

const summaryHeaders = [
  { title: 'No. MR', key: 'no_mr' }, { title: 'Nama Pasien', key: 'nama_pasien' }, { title: 'Jaminan', key: 'jaminan' },
  { title: 'QC', key: 'qc', align: 'center' }, { title: 'Edukasi', key: 'edukasi', align: 'center' },
  { title: 'Batal Ranap', key: 'batal', align: 'center' }, { title: 'Up Selling', key: 'up', align: 'center' },
  { title: 'Tanggal', key: 'tanggal' },
]
const qcHeaders = [
  { title: 'Tanggal', key: 'tanggal' }, { title: 'No. MR', key: 'no_mr' }, { title: 'Nama Pasien', key: 'nama_pasien' },
  { title: 'Status', key: 'status', align: 'center' }, { title: 'Petugas', key: 'petugas' }, { title: 'Durasi', key: 'durasi_tunggu', align: 'center' },
]
const batalHeaders = [
  { title: 'Tanggal', key: 'tanggal' }, { title: 'No. Reg', key: 'no_reg' }, { title: 'Nama Pasien', key: 'nama_pasien' },
  { title: 'Ket. Batal', key: 'keterangan_batal' }, { title: 'Status OK', key: 'status_ok', align: 'center' },
  { title: 'Closing', key: 'status_closing', align: 'center' }, { title: 'Petugas', key: 'petugas' },
]
const edukasiHeaders = [
  { title: 'Tanggal', key: 'tanggal' }, { title: 'No. MR', key: 'no_mr' }, { title: 'Nama Pasien', key: 'nama_pasien' },
  { title: 'Status', key: 'status', align: 'center' }, { title: 'Petugas', key: 'petugas' },
]
const upHeaders = [
  { title: 'tgldaftar', key: 'tgl_daftar' }, { title: 'NoReg', key: 'no_reg' }, { title: 'NamaPasien', key: 'nama_pasien' },
  { title: 'Ket Up Selling', key: 'alasan' }, { title: 'notes', key: 'note' }, { title: 'Petugas', key: 'petugas' },
]
const activeHeaders = computed(() => ({summary:summaryHeaders,'quality-control':qcHeaders,'batal-ranap':batalHeaders,'edukasi-lanjutan':edukasiHeaders,'up-selling':upHeaders})[activeTab.value] ?? [])

// Set default tab berdasarkan role — hanya saat pertama kali (tidak override klik user)
let tabInitialized = false
onMounted(() => {
  if (!tabInitialized) {
    activeTab.value = isKasir.value ? 'batal-ranap' : 'summary'
    tabInitialized = true
  }
  loadAll()
})

// Re-load jika role berubah (restore session), jangan overwrite tab yang sudah di-klik
watch(() => auth.userRole, (role, prev) => {
  if (role && role !== prev) {
    // Hanya set tab default jika belum ada tab aktif yang valid
    if (!tabInitialized) {
      activeTab.value = role === 'kasir' ? 'batal-ranap' : 'summary'
      tabInitialized = true
    }
    loadAll()
  }
})
</script>

<template>
  <div>
    <!-- Header -->
    <div class="page-hero page-hero--view">
      <div class="page-hero__content">
        <div class="page-hero__badge"><VIcon icon="ri-table-line" size="12" />View Data Input</div>
        <h1 class="page-hero__title">View Data Input</h1>
        <p class="page-hero__subtitle">{{ isKasir ? 'Data batal ranap real-time' : 'Summary semua data input per modul' }}</p>
      </div>
      <div class="page-hero__actions">
        <VBtn icon variant="text" color="white" size="small" :loading="loading" @click="loadAll"><VIcon icon="ri-refresh-line" /></VBtn>
        <VBtn v-if="!isKasir" color="white" variant="elevated" rounded="pill" size="small" style="color:#0F4C35;font-weight:700" @click="exportCSV">
          <VIcon icon="ri-download-line" size="15" class="me-1" />Export CSV
        </VBtn>
      </div>
      <VIcon icon="ri-table-line" class="page-hero__icon" />
    </div>

    <!-- Stats (non-kasir) -->
    <SummaryCards v-if="!isKasir"
      v-model="activeTab"
      :cards="[
        { value: grandStats.qc,      label: 'Quality Control',  color: 'primary', icon: 'ri-shield-check-line',    filterValue: 'quality-control' },
        { value: grandStats.batal,   label: 'Batal Ranap',      color: 'error',   icon: 'ri-close-circle-line',    filterValue: 'batal-ranap' },
        { value: grandStats.edukasi, label: 'Edukasi Lanjutan', color: 'warning', icon: 'ri-book-open-line',       filterValue: 'edukasi-lanjutan' },
        { value: grandStats.up,      label: 'Up Selling',       color: 'success', icon: 'ri-arrow-up-circle-line', filterValue: 'up-selling' },
      ]"
    />

    <!-- Stats kasir -->
    <SummaryCards v-else :cards="[
      { value: batalData.length,                                          label: 'Total', color: 'primary', icon: 'ri-close-circle-line' },
      { value: batalData.filter(r=>r.status_closing==='Siap Closing').length, label: 'Siap Closing', color: 'success', icon: 'ri-checkbox-circle-line' },
      { value: batalData.filter(r=>!r.status_closing).length,            label: 'Belum Closing', color: 'warning', icon: 'ri-time-line' },
    ]" />

    <!-- Tabs & Filter -->
    <VCard elevation="0" border rounded="xl" class="mb-4">
      <VTabs v-model="activeTab" color="primary" show-arrows density="compact">
        <VTab v-for="t in tabs" :key="t.key" :value="t.key" class="text-caption">
          <VIcon :icon="t.icon" size="15" class="me-1" />{{ t.label }}
        </VTab>
      </VTabs>
      <VDivider />
      <VCardText class="pa-3">
        <VRow dense align="center">
          <VCol cols="12" sm="5">
            <VTextField v-model="search" label="Cari..." prepend-inner-icon="ri-search-line"
              variant="outlined" density="compact" hide-details clearable rounded="lg" />
          </VCol>
          <VCol cols="6" sm="3">
            <VTextField v-model="dateFrom" label="Dari" type="date" variant="outlined" density="compact" hide-details rounded="lg" />
          </VCol>
          <VCol cols="6" sm="3">
            <VTextField v-model="dateTo" label="Sampai" type="date" variant="outlined" density="compact" hide-details rounded="lg" />
          </VCol>
          <VCol cols="auto">
            <VBtn size="small" variant="text" color="secondary" @click="search='';dateFrom='';dateTo=''">Reset</VBtn>
          </VCol>
        </VRow>
      </VCardText>
    </VCard>

    <!-- Count -->
    <div class="d-flex align-center gap-3 mb-4">
      <VChip size="small" color="primary" variant="tonal" rounded="pill">{{ filteredData.length }} data</VChip>
      <VProgressCircular v-if="loading" size="16" width="2" indeterminate color="primary" />
    </div>

    <!-- Table -->
    <VCard elevation="0" border rounded="xl">
      <VDataTable
        :headers="activeHeaders"
        :items="filteredData"
        density="comfortable"
        hover
        :loading="loading"
        :items-per-page="20"
      >
        <!-- Status chips -->
        <template #item.status="{ item }">
          <VChip :color="statusColor(item.status)" size="x-small" variant="tonal">{{ item.status || '—' }}</VChip>
        </template>
        <template #item.status_ok="{ item }">
          <VChip :color="statusColor(item.status_ok)" size="x-small" variant="tonal">{{ item.status_ok || '—' }}</VChip>
        </template>
        <template #item.status_closing="{ item }">
          <VChip v-if="item.status_closing" :color="item.status_closing==='Siap Closing'?'success':'error'" size="x-small" variant="tonal">{{ item.status_closing }}</VChip>
          <span v-else class="text-caption" style="color:var(--qc-text-2)">—</span>
        </template>
        <!-- Summary counts -->
        <template #item.qc="{ item }"><VChip size="x-small" color="primary" variant="tonal">{{ item.qc }}</VChip></template>
        <template #item.edukasi="{ item }"><VChip size="x-small" color="warning" variant="tonal">{{ item.edukasi }}</VChip></template>
        <template #item.batal="{ item }"><VChip size="x-small" color="error" variant="tonal">{{ item.batal }}</VChip></template>
        <template #item.up="{ item }"><VChip size="x-small" color="success" variant="tonal">{{ item.up }}</VChip></template>
        <!-- Nama dengan avatar -->
        <template #item.nama_pasien="{ item }">
          <div class="d-flex align-center gap-2">
            <VAvatar color="primary" variant="tonal" size="24" rounded="md">
              <span style="font-size:10px;font-weight:700">{{ item.nama_pasien?.charAt(0)?? '?' }}</span>
            </VAvatar>
            <span class="text-body-2">{{ item.nama_pasien }}</span>
          </div>
        </template>
        <template #no-data>
          <div class="text-center py-12" style="color:var(--qc-text-2)">
            <VIcon icon="ri-inbox-line" size="40" class="mb-2 opacity-30" />
            <p class="text-body-2 font-weight-medium mb-0">Belum ada data</p>
          </div>
        </template>
      </VDataTable>
    </VCard>
  </div>
</template>
