<script setup>
import axios from 'axios'
import { useAuthStore } from '@/stores/useAuthStore'
import { useDisplay } from 'vuetify'
import SummaryCards from '@/components/SummaryCards.vue'
import PageHero from '@/components/PageHero.vue'

const auth = useAuthStore()
const { xs } = useDisplay()
const loading = ref(false)
const search = ref('')

function todayStr() {
  const d = new Date(), p = n => String(n).padStart(2, '0')
  return `${d.getFullYear()}-${p(d.getMonth() + 1)}-${p(d.getDate())}`
}

const todayFormatted = computed(() =>
  new Date().toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' })
)

const dateFrom = ref(todayStr())
const dateTo = ref(todayStr())
const filterClosing = ref('')
const activeTab = ref('batal-ranap')

const CLOSING_OPTIONS = [
  { title: 'Semua Status', value: '' },
  { title: '👍 Siap Closing', value: 'Siap Closing' },
  { title: '⏳ Belum Siap', value: 'Belum Siap Closing' },
]

const isKasir = computed(() => !auth.hasPermission('quality-control:view'))

const allTabs = [
  { key: 'summary',          label: 'Summary',          shortLabel: 'Summary', icon: 'ri-user-heart-line',       permission: 'quality-control:view' },
  { key: 'quality-control',  label: 'Quality Control',  shortLabel: 'QC',      icon: 'ri-shield-check-line',     permission: 'quality-control:view' },
  { key: 'batal-ranap',      label: 'Batal Ranap',      shortLabel: 'Batal',   icon: 'ri-close-circle-line',     permission: 'batal-ranap:view' },
  { key: 'edukasi-lanjutan', label: 'Edukasi Lanjutan', shortLabel: 'Edukasi', icon: 'ri-book-open-line',        permission: 'edukasi-lanjutan:view' },
  { key: 'up-selling',       label: 'Up Selling',       shortLabel: 'Up Sell', icon: 'ri-arrow-up-circle-line',  permission: 'up-selling:view' },
]

const tabs = computed(() => allTabs.filter(t => auth.hasPermission(t.permission)))

const qcData = ref([])
const batalData = ref([])
const edukasiData = ref([])
const upData = ref([])

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
        safeGet('/api/quality-control', { per_page: 500 }),
        safeGet('/api/batal-ranap', { per_page: 500 }),
        safeGet('/api/edukasi-lanjutan', { per_page: 500 }),
        safeGet('/api/up-selling', { per_page: 500 }),
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
  const merge = (list, countKey, tglKey = 'tanggal') => list.forEach(r => {
    const k = r.no_mr ?? r.no_reg ?? 'x'
    if (!map[k]) map[k] = { no_mr: k, nama_pasien: r.nama_pasien, jaminan: r.jaminan || '—', qc: 0, edukasi: 0, batal: 0, up: 0, tanggal: r[tglKey] }
    map[k][countKey]++
    if (r[tglKey] > (map[k].tanggal || '')) map[k].tanggal = r[tglKey]
  })
  merge(qcData.value, 'qc'); merge(edukasiData.value, 'edukasi')
  merge(batalData.value, 'batal'); merge(upData.value, 'up')
  return Object.values(map)
})

const activeData = computed(() => {
  const map = { summary: summaryData.value, 'quality-control': qcData.value, 'batal-ranap': batalData.value, 'edukasi-lanjutan': edukasiData.value, 'up-selling': upData.value }
  return map[activeTab.value] ?? []
})

function parseTanggal(str) {
  if (!str) return null
  const dmyMatch = str.match(/^(\d{2})\/(\d{2})\/(\d{4})/)
  if (dmyMatch) return new Date(`${dmyMatch[3]}-${dmyMatch[2]}-${dmyMatch[1]}`)
  const d = new Date(str)
  return isNaN(d) ? null : d
}

const filteredData = computed(() => {
  let d = activeData.value
  if (search.value.trim()) {
    const q = search.value.toLowerCase()
    d = d.filter(r => Object.values(r).some(v => String(v ?? '').toLowerCase().includes(q)))
  }
  if (dateFrom.value || dateTo.value) {
    const from = dateFrom.value ? new Date(dateFrom.value + 'T00:00:00') : null
    const to = dateTo.value ? new Date(dateTo.value + 'T23:59:59') : null
    d = d.filter(r => {
      const tgl = parseTanggal(r.tanggal || r.tgl_daftar || '')
      if (!tgl) return true
      if (from && tgl < from) return false
      if (to && tgl > to) return false
      return true
    })
  }
  if (activeTab.value === 'batal-ranap' && filterClosing.value !== '') {
    if (filterClosing.value === null) {
      d = d.filter(r => !r.status_closing)
    } else {
      d = d.filter(r => r.status_closing === filterClosing.value)
    }
  }
  return d
})

function statusColor(s) {
  return ({ 'Edukasi': 'success', 'Edukasi lanjutan': 'warning', 'Bedah': 'success', 'Non Bedah': 'info', 'Selesai': 'success', 'Menunggu': 'warning', 'Berhasil': 'success', 'Tidak Berhasil': 'error', 'Pending': 'warning' })[s] ?? 'secondary'
}

function closingColor(s) {
  return s === 'Siap Closing' ? 'success' : s === 'Belum Siap Closing' ? 'error' : 'secondary'
}

const pageBannerColor = computed(() => ({
  'summary': 'primary',
  'quality-control': 'primary',
  'batal-ranap': 'error',
  'edukasi-lanjutan': 'warning',
  'up-selling': 'success',
})[activeTab.value] ?? 'primary')

function exportCSV() {
  const cols = activeHeaders.value
  const csv = [cols.map(h => h.title).join(','), ...filteredData.value.map(r => cols.map(h => `"${r[h.key] ?? ''}"`).join(','))].join('\n')
  const a = document.createElement('a'); a.href = URL.createObjectURL(new Blob(['\uFEFF' + csv], { type: 'text/csv' }))
  a.download = `view-${activeTab.value}-${new Date().toISOString().slice(0, 10)}.csv`; a.click()
}

// Header definitions (for CSV export only now)
const summaryHeaders = [
  { title: 'No. MR', key: 'no_mr' }, { title: 'Nama Pasien', key: 'nama_pasien' }, { title: 'Jaminan', key: 'jaminan' },
  { title: 'QC', key: 'qc' }, { title: 'Edukasi', key: 'edukasi' },
  { title: 'Batal Ranap', key: 'batal' }, { title: 'Up Selling', key: 'up' }, { title: 'Tanggal', key: 'tanggal' },
]
const qcHeaders = [
  { title: 'Tanggal', key: 'tanggal' }, { title: 'No. MR', key: 'no_mr' }, { title: 'Nama Pasien', key: 'nama_pasien' },
  { title: 'Status', key: 'status' }, { title: 'Petugas', key: 'petugas' }, { title: 'Durasi', key: 'durasi_tunggu' },
]
const batalHeaders = [
  { title: 'Tanggal', key: 'tanggal' }, { title: 'No. Reg', key: 'no_reg' }, { title: 'Nama Pasien', key: 'nama_pasien' },
  { title: 'Ket. Batal', key: 'keterangan_batal' }, { title: 'Status OK', key: 'status_ok' },
  { title: 'Closing', key: 'status_closing' }, { title: 'Petugas', key: 'petugas' },
]
const edukasiHeaders = [
  { title: 'Tanggal', key: 'tanggal' }, { title: 'No. MR', key: 'no_mr' }, { title: 'Nama Pasien', key: 'nama_pasien' },
  { title: 'Status', key: 'status' }, { title: 'Petugas', key: 'petugas' },
]
const upHeaders = [
  { title: 'Tgl Daftar', key: 'tgl_daftar' }, { title: 'No. Reg', key: 'no_reg' }, { title: 'Nama Pasien', key: 'nama_pasien' },
  { title: 'Keterangan', key: 'alasan' }, { title: 'Notes', key: 'note' }, { title: 'Petugas', key: 'petugas' },
]
const activeHeaders = computed(() => ({ summary: summaryHeaders, 'quality-control': qcHeaders, 'batal-ranap': batalHeaders, 'edukasi-lanjutan': edukasiHeaders, 'up-selling': upHeaders })[activeTab.value] ?? [])

let tabInitialized = false
onMounted(() => {
  if (!tabInitialized) {
    activeTab.value = auth.hasPermission('quality-control:view') ? 'summary' : 'batal-ranap'
    tabInitialized = true
  }
  loadAll()
})

watch(() => auth.permissions, (perms, prev) => {
  if (perms?.length && perms !== prev) {
    if (!tabInitialized) {
      activeTab.value = auth.hasPermission('quality-control:view') ? 'summary' : 'batal-ranap'
      tabInitialized = true
    }
    loadAll()
  }
})
</script>

<template>
  <div>
    <!-- Header -->
    <PageHero icon="ri-table-line" badge="View Data Input" title="View Data Input"
      :subtitle="isKasir ? 'Data batal ranap real-time' : 'Summary semua data input per modul'" color-from="#0EA5E9"
      color-to="#0369A1" :pills="[
        { icon: 'ri-calendar-line', text: todayFormatted },
        { icon: 'ri-database-line', text: `${filteredData.length} data` },
      ]">
      <template #actions>
        <VBtn v-if="!isKasir" color="white" variant="elevated" rounded="pill" size="small"
          style="color:#0369A1;font-weight:700" @click="exportCSV">
          <VIcon icon="ri-download-line" size="15" class="me-1" />Export CSV
        </VBtn>
      </template>
    </PageHero>

    <!-- Stats -->
    <SummaryCards v-if="!isKasir" v-model="activeTab" :cards="[
      { value: grandStats.qc, label: 'Quality Control', color: 'primary', icon: 'ri-shield-check-line', filterValue: 'quality-control' },
      { value: grandStats.batal, label: 'Batal Ranap', color: 'error', icon: 'ri-close-circle-line', filterValue: 'batal-ranap' },
      { value: grandStats.edukasi, label: 'Edukasi Lanjutan', color: 'warning', icon: 'ri-book-open-line', filterValue: 'edukasi-lanjutan' },
      { value: grandStats.up, label: 'Up Selling', color: 'success', icon: 'ri-arrow-up-circle-line', filterValue: 'up-selling' },
    ]" />
    <SummaryCards v-else :cards="[
      { value: batalData.length, label: 'Total', color: 'primary', icon: 'ri-close-circle-line' },
      { value: batalData.filter(r => r.status_closing === 'Siap Closing').length, label: 'Siap Closing', color: 'success', icon: 'ri-checkbox-circle-line' },
      { value: batalData.filter(r => !r.status_closing).length, label: 'Belum Closing', color: 'warning', icon: 'ri-time-line' },
    ]" />

    <!-- Tabs + Filter dalam 1 card -->
    <VCard elevation="0" border rounded="xl" class="mb-4">
      <!-- Tabs — scrollable, short label on mobile -->
      <VTabs v-model="activeTab" color="primary" show-arrows density="compact" class="vdi-tabs">
        <VTab v-for="t in tabs" :key="t.key" :value="t.key" class="vdi-tab">
          <VIcon :icon="t.icon" size="15" class="me-1" />
          {{ xs ? t.shortLabel : t.label }}
        </VTab>
      </VTabs>
      <VDivider />

      <!-- Penanda halaman aktif -->
      <div class="vdi-page-banner" :class="`vdi-page-banner--${activeTab}`">
        <VIcon :icon="tabs.find(t => t.key === activeTab)?.icon ?? 'ri-table-line'" size="14" class="me-1" />
        <span>Anda sedang melihat: <strong>{{tabs.find(t => t.key === activeTab)?.label ?? activeTab}}</strong></span>
        <VChip size="x-small" variant="tonal" :color="pageBannerColor" class="ms-2">{{ filteredData.length }} data
        </VChip>
      </div>

      <VDivider />

      <!-- Filter row -->
      <VCardText class="pa-3">
        <VRow dense align="center" class="g-2">
          <!-- Search -->
          <VCol cols="12" sm="4" md="4">
            <VTextField v-model="search" label="Cari..." prepend-inner-icon="ri-search-line" variant="outlined"
              density="compact" hide-details clearable rounded="lg" />
          </VCol>
          <!-- Date From -->
          <VCol cols="6" sm="3" md="2">
            <VTextField v-model="dateFrom" label="Dari" type="date" variant="outlined" density="compact" hide-details
              rounded="lg" />
          </VCol>
          <!-- Date To -->
          <VCol cols="6" sm="3" md="2">
            <VTextField v-model="dateTo" label="Sampai" type="date" variant="outlined" density="compact" hide-details
              rounded="lg" />
          </VCol>
          <!-- Status Closing dropdown (hanya batal-ranap) -->
          <VCol v-if="activeTab === 'batal-ranap'" cols="12" sm="auto" md="3">
            <VSelect v-model="filterClosing" :items="CLOSING_OPTIONS" item-title="title" item-value="value"
              label="Status Closing" variant="outlined" density="compact" hide-details rounded="lg" />
          </VCol>
          <!-- Reset -->
          <VCol cols="auto">
            <VBtn size="small" variant="text" color="secondary"
              @click="search = ''; dateFrom = todayStr(); dateTo = todayStr(); filterClosing = ''">
              Reset
            </VBtn>
          </VCol>
        </VRow>
      </VCardText>
    </VCard>

    <!-- Count bar -->
    <div class="d-flex align-center gap-3 mb-3">
      <VChip size="small" color="primary" variant="tonal" rounded="pill">{{ filteredData.length }} data</VChip>
      <VProgressCircular v-if="loading" size="16" width="2" indeterminate color="primary" />
    </div>

    <!-- ── LIST VIEW — responsif, no horizontal scroll ─── -->
    <VCard elevation="0" border rounded="xl" class="overflow-hidden">
      <!-- Loading -->
      <div v-if="loading" class="text-center py-12">
        <VProgressCircular indeterminate color="primary" size="32" />
      </div>

      <!-- Empty -->
      <div v-else-if="!filteredData.length" class="text-center py-16" style="color:var(--qc-text-2)">
        <VIcon icon="ri-inbox-line" size="52" class="mb-3 opacity-30" />
        <p class="text-body-1 font-weight-semibold mb-1">Belum ada data</p>
      </div>

      <!-- Items -->
      <div v-else>
        <div v-for="(item, idx) in filteredData" :key="item.id ?? idx" class="vdi-row"
          :class="{ 'vdi-row--bordered': idx < filteredData.length - 1 }">
          <!-- Avatar -->
          <VAvatar color="primary" variant="tonal" size="38" rounded="md" class="flex-shrink-0">
            <span style="font-size:12px;font-weight:700">{{ item.nama_pasien?.charAt(0) ?? '?' }}</span>
          </VAvatar>

          <!-- Content -->
          <div class="flex-grow-1 min-width-0">
            <!-- Row 1: nama + chips status -->
            <div class="d-flex align-center gap-2 flex-wrap mb-1">
              <span class="font-weight-semibold text-truncate" style="font-size:0.875rem;color:var(--qc-text)">
                {{ item.nama_pasien }}
              </span>

              <!-- Summary tab: count chips -->
              <template v-if="activeTab === 'summary'">
                <VChip v-if="item.qc" size="x-small" color="primary" variant="tonal">QC {{ item.qc }}</VChip>
                <VChip v-if="item.edukasi" size="x-small" color="warning" variant="tonal">Edu {{ item.edukasi }}</VChip>
                <VChip v-if="item.batal" size="x-small" color="error" variant="tonal">Batal {{ item.batal }}</VChip>
                <VChip v-if="item.up" size="x-small" color="success" variant="tonal">Up {{ item.up }}</VChip>
              </template>

              <!-- QC tab -->
              <template v-else-if="activeTab === 'quality-control'">
                <VChip v-if="item.status" :color="statusColor(item.status)" size="x-small" variant="tonal">{{
                  item.status }}</VChip>
                <VChip v-if="item.durasi_tunggu" size="x-small" color="secondary" variant="tonal">
                  <VIcon icon="ri-time-line" size="10" class="me-1" />{{ item.durasi_tunggu }}
                </VChip>
              </template>

              <!-- Batal Ranap tab -->
              <template v-else-if="activeTab === 'batal-ranap'">
                <VChip v-if="item.status_ok" :color="statusColor(item.status_ok)" size="x-small" variant="tonal">{{
                  item.status_ok }}</VChip>
                <VChip v-if="item.status_closing" :color="closingColor(item.status_closing)" size="x-small"
                  variant="tonal">{{ item.status_closing }}</VChip>
                <span v-else class="text-caption" style="color:var(--qc-text-2);font-size:0.7rem">Belum closing</span>
              </template>

              <!-- Edukasi tab -->
              <template v-else-if="activeTab === 'edukasi-lanjutan'">
                <VChip v-if="item.status" :color="statusColor(item.status)" size="x-small" variant="tonal">{{
                  item.status }}</VChip>
              </template>

              <!-- Up Selling tab -->
              <template v-else-if="activeTab === 'up-selling'">
                <VChip v-if="item.alasan" :color="item.alasan === 'Naik Kelas' ? 'success' : 'info'" size="x-small"
                  variant="tonal">{{ item.alasan }}</VChip>
              </template>
            </div>

            <!-- Row 2: meta info -->
            <div class="d-flex align-center gap-3 flex-wrap">
              <span class="text-caption" style="color:var(--qc-text-2)">
                {{ item.no_mr || item.no_reg || '—' }}
              </span>
              <span v-if="activeTab === 'summary' && item.jaminan" class="text-caption"
                style="color:var(--qc-text-2)">{{
                item.jaminan }}</span>
              <span v-if="activeTab === 'batal-ranap' && item.keterangan_batal" class="text-caption text-truncate"
                style="color:var(--qc-text-2);max-width:160px">
                <VIcon icon="ri-error-warning-line" size="10" class="me-1" />{{ item.keterangan_batal }}
              </span>
              <span v-if="activeTab === 'up-selling' && item.note" class="text-caption text-truncate"
                style="color:var(--qc-text-2);max-width:140px">{{ item.note }}</span>
            </div>
          </div>

          <!-- Right: petugas + tanggal -->
          <div class="text-end flex-shrink-0 vdi-right">
            <p class="text-caption mb-0 font-weight-medium" style="color:var(--qc-text)">{{ item.petugas || '—' }}</p>
            <p class="text-caption mb-0" style="color:var(--qc-text-2)">{{ item.tanggal || item.tgl_daftar || '—' }}</p>
          </div>
        </div>
      </div>
    </VCard>
  </div>
</template>

<style scoped>
/* ── List row ── */
.vdi-row {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 14px 16px;
  transition: background 0.12s;
  border-bottom: 1px solid var(--qc-border, rgba(0, 0, 0, 0.07));
}

.vdi-row:last-child {
  border-bottom: none;
}

.vdi-row:hover {
  background: rgba(14, 165, 233, 0.04);
}

/* Subtle left accent on hover */
.vdi-row:hover {
  border-left: 3px solid rgba(14, 165, 233, 0.4);
  padding-left: 13px;
}

.vdi-tab {
  font-size: 0.8rem !important;
  min-width: 0 !important;
}

.vdi-right {
  min-width: 80px;
  max-width: 110px;
}

@media (max-width: 480px) {
  .vdi-right {
    display: none;
  }

  .vdi-row {
    padding: 12px 12px;
  }
}

/* ── Page banner (penanda halaman aktif) ── */
.vdi-page-banner {
  display: flex;
  align-items: center;
  padding: 7px 16px;
  font-size: 0.78rem;
  color: var(--qc-text-2, #64748b);
  background: rgba(14, 165, 233, 0.04);
  transition: background 0.2s, color 0.2s;
}

.vdi-page-banner strong {
  color: var(--qc-text, #1e293b);
}

.vdi-page-banner--quality-control {
  background: rgba(99, 102, 241, 0.06);
}

.vdi-page-banner--batal-ranap {
  background: rgba(239, 68, 68, 0.06);
}

.vdi-page-banner--edukasi-lanjutan {
  background: rgba(245, 158, 11, 0.06);
}

.vdi-page-banner--up-selling {
  background: rgba(34, 197, 94, 0.06);
}

.vdi-page-banner--summary {
  background: rgba(14, 165, 233, 0.06);
}
</style>
