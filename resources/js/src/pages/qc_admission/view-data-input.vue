<script setup>
import axios from 'axios'
import { useAuthStore } from '@/stores/useAuthStore'
import { useDisplay } from 'vuetify'
import SummaryCards from '@/components/SummaryCards.vue'
import PageHero from '@/components/PageHero.vue'
import VdiDetailDialog from '@/views/qc-admission/view-data-input/VdiDetailDialog.vue'
import PaginationBar from '@/components/PaginationBar.vue'
import { usePagination } from '@/composables/usePagination'

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
const filterAlasan = ref('')
const activeTab = ref('summary')

const isKasir = computed(() => !auth.hasPermission('quality-control:view'))

// ── Hanya 3 tab sekarang ──────────────────────────────────────────────────────
const allTabs = [
  { key: 'summary',         label: 'Summary',         shortLabel: 'Summary', icon: 'ri-bar-chart-box-line',   permission: 'quality-control:view' },
  { key: 'alasan',          label: 'Alasan',           shortLabel: 'Alasan',  icon: 'ri-question-answer-line', permission: 'alasan:view' },
  { key: 'history-pasien',  label: 'History Pasien',   shortLabel: 'History', icon: 'ri-time-line',            permission: 'quality-control:view' },
]

const tabs = computed(() => allTabs.filter(t => auth.hasPermission(t.permission)))

const qcData = ref([])
const batalData = ref([])
const edukasiData = ref([])
const edukasiTransferData = ref([])
const upData = ref([])
const alasanData = ref([])

async function safeGet(url, params = {}) {
  try {
    const { data } = await axios.get(url, { params })
    return data.data ?? []
  } catch { return [] }
}

async function loadAll() {
  loading.value = true
  try {
    const dateParams = {
      date_from: dateFrom.value || undefined,
      date_to:   dateTo.value   || undefined,
    }

    if (isKasir.value) {
      batalData.value = await safeGet('/api/batal-ranap', { per_page: 300, ...dateParams })
    } else {
      const promises = [
        safeGet('/api/quality-control', { per_page: 300, ...dateParams }),
        safeGet('/api/batal-ranap',     { per_page: 300, ...dateParams }),
        axios.get('/api/edukasi-lanjutan/split', { params: { per_page: 300, ...dateParams } })
          .then(r => r.data).catch(() => ({ active: [], transferred: [] })),
        safeGet('/api/up-selling', { per_page: 300, ...dateParams }),
      ]
      if (auth.hasPermission('alasan:view')) {
        promises.push(safeGet('/api/alasan', { per_page: 300, ...dateParams }))
      }

      const [qc, batal, eduSplit, up, alasan] = await Promise.all(promises)
      qcData.value              = qc
      batalData.value           = batal
      edukasiData.value         = eduSplit.active      ?? []
      edukasiTransferData.value = eduSplit.transferred ?? []
      upData.value              = up
      alasanData.value          = alasan ?? []
    }
  } finally { loading.value = false }
}

// ── Summary per pasien (gabungan semua modul) ─────────────────────────────────
const summaryData = computed(() => {
  const map = {}
  const merge = (list, countKey, tglKey = 'tanggal') => list.forEach(r => {
    const k = r.no_mr ?? r.no_reg ?? 'x'
    if (!map[k]) map[k] = { no_mr: k, nama_pasien: r.nama_pasien, jaminan: r.jaminan || '—', qc: 0, edukasi: 0, batal: 0, up: 0, alasan: 0, tanggal: r[tglKey] }
    map[k][countKey]++
    if (r[tglKey] > (map[k].tanggal || '')) map[k].tanggal = r[tglKey]
  })
  merge(qcData.value, 'qc')
  merge(edukasiData.value, 'edukasi')
  merge(batalData.value, 'batal')
  merge(upData.value, 'up')
  merge(alasanData.value, 'alasan')
  return Object.values(map)
})

// ── History Pasien: gabungkan SEMUA modul per no_mr ───────────────────────────
// Setiap pasien punya entry + array _events (seluruh aktivitas lintas modul)
const historyPasienData = computed(() => {
  const map = {}

  function ensurePatient(r, tglKey = 'tanggal') {
    const key = r.no_mr || r.no_reg || r.id
    if (!map[key]) {
      map[key] = {
        no_mr: r.no_mr || r.no_reg,
        no_reg: r.no_reg,
        nama_pasien: r.nama_pasien,
        jaminan: r.jaminan,
        tanggal: r[tglKey],
        _events: [],
      }
    }
    return key
  }

  // QC / Edukasi Awal
  for (const r of qcData.value) {
    const key = ensurePatient(r)
    map[key]._events.push({ _module: 'edukasi-awal', _label: 'Edukasi Awal', ...r })
    if (r.tanggal > (map[key].tanggal || '')) map[key].tanggal = r.tanggal
  }

  // Edukasi Lanjutan (active = belum dapat bed)
  for (const r of edukasiData.value) {
    const key = ensurePatient(r)
    map[key]._events.push({ _module: 'edukasi-lanjutan', _label: 'Edukasi Lanjutan', ...r })
    if (r.tanggal > (map[key].tanggal || '')) map[key].tanggal = r.tanggal
  }

  // Edukasi Lanjutan transferred (sudah dapat bed)
  for (const r of edukasiTransferData.value) {
    const key = ensurePatient(r)
    map[key]._events.push({ _module: 'sudah-dapat-bed', _label: 'Sudah Dapat Bed', ...r })
    if (r.tanggal > (map[key].tanggal || '')) map[key].tanggal = r.tanggal
  }

  // Batal Ranap
  for (const r of batalData.value) {
    const key = ensurePatient(r, 'tanggal')
    map[key]._events.push({ _module: 'batal-ranap', _label: 'Batal Ranap', ...r })
    if (r.tanggal > (map[key].tanggal || '')) map[key].tanggal = r.tanggal
  }

  // Up Selling
  for (const r of upData.value) {
    const key = ensurePatient(r, 'tgl_daftar')
    map[key]._events.push({ _module: 'up-selling', _label: 'Up Selling', ...r })
    const tgl = r.tgl_daftar || r.tanggal
    if (tgl > (map[key].tanggal || '')) map[key].tanggal = tgl
  }

  // Urutkan events per pasien: terbaru dulu
  for (const p of Object.values(map)) {
    p._events.sort((a, b) => {
      const da = a.tanggal || a.tgl_daftar || ''
      const db = b.tanggal || b.tgl_daftar || ''
      return db.localeCompare(da)
    })
  }

  // Urutkan pasien: terbaru aktivitas dulu
  return Object.values(map).sort((a, b) => (b.tanggal || '').localeCompare(a.tanggal || ''))
})

const grandStats = computed(() => ({
  qc:             qcData.value.length,
  batal:          batalData.value.length,
  edukasi:        edukasiData.value.length,
  edukasiTransfer: edukasiTransferData.value.length,
  up:             upData.value.length,
  alasan:         alasanData.value.length,
  historyPasien:  historyPasienData.value.length,
}))

// Daftar unik alasan untuk dropdown filter
const alasanOptions = computed(() => {
  const unique = [...new Set(alasanData.value.map(r => r.alasan).filter(Boolean))]
  return [{ title: 'Semua Alasan', value: '' }, ...unique.map(a => ({ title: a, value: a }))]
})

const activeData = computed(() => {
  const map = {
    summary:        summaryData.value,
    alasan:         alasanData.value,
    'history-pasien': historyPasienData.value,
  }
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
    d = d.filter(r => {
      const flatMatch = Object.entries(r).some(([k, v]) => {
        if (Array.isArray(v) || typeof v === 'object') return false
        return String(v ?? '').toLowerCase().includes(q)
      })
      // Juga cari dalam events history
      if (!flatMatch && r._events) {
        return r._events.some(ev =>
          Object.entries(ev).some(([k, v]) => {
            if (Array.isArray(v) || typeof v === 'object') return false
            return String(v ?? '').toLowerCase().includes(q)
          })
        )
      }
      return flatMatch
    })
  }
  if (dateFrom.value || dateTo.value) {
    const from = dateFrom.value ? new Date(dateFrom.value + 'T00:00:00') : null
    const to   = dateTo.value   ? new Date(dateTo.value   + 'T23:59:59') : null
    d = d.filter(r => {
      const tgl = parseTanggal(r.tanggal || r.tgl_daftar || '')
      if (!tgl) return true
      if (from && tgl < from) return false
      if (to   && tgl > to)   return false
      return true
    })
  }
  if (activeTab.value === 'alasan' && filterAlasan.value !== '') {
    d = d.filter(r => r.alasan === filterAlasan.value)
  }
  return d
})

// ── Alasan summary ring chart data ───────────────────────────────────────────
const filteredAlasanSummary = computed(() => {
  const base = activeTab.value === 'alasan' ? filteredData.value : alasanData.value
  const map = {}
  for (const r of base) {
    const key = r.alasan || 'Tidak Diketahui'
    if (!map[key]) map[key] = { alasan: key, total: 0, jaminanMap: {} }
    map[key].total++
    const jam = r.jaminan || 'Lainnya'
    map[key].jaminanMap[jam] = (map[key].jaminanMap[jam] || 0) + 1
  }
  return Object.values(map).sort((a, b) => b.total - a.total)
})

// ── Color helpers ─────────────────────────────────────────────────────────────
function alasanColor(a) {
  return ({ 'Pelayanan': 'primary', 'Kelengkapan Alat & Dokter': 'warning', 'Teman/Kerabat': 'info', 'Rujukan': 'success', 'Marketing': 'secondary', 'Sosial Media': 'info' })[a] ?? 'secondary'
}

function statusColor(s) {
  return ({ 'Edukasi': 'success', 'Edukasi lanjutan': 'warning', 'Bedah': 'success', 'Non Bedah': 'info', 'Selesai': 'success', 'Menunggu': 'warning', 'Berhasil': 'success', 'Tidak Berhasil': 'error', 'Pending': 'warning' })[s] ?? 'secondary'
}

// Warna + config per modul untuk History Pasien
const MODULE_META = {
  'edukasi-awal':     { color: 'primary',   icon: 'ri-shield-check-line',    bg: 'rgba(99,102,241,0.10)',  border: '#6366f1' },
  'edukasi-lanjutan': { color: 'warning',   icon: 'ri-book-open-line',       bg: 'rgba(245,158,11,0.10)', border: '#f59e0b' },
  'sudah-dapat-bed':  { color: 'success',   icon: 'ri-home-heart-line',      bg: 'rgba(34,197,94,0.10)',  border: '#22c55e' },
  'batal-ranap':      { color: 'error',     icon: 'ri-close-circle-line',    bg: 'rgba(239,68,68,0.10)',  border: '#ef4444' },
  'up-selling':       { color: 'orange',    icon: 'ri-arrow-up-circle-line', bg: 'rgba(249,115,22,0.10)', border: '#f97316' },
}

function moduleMeta(mod) {
  return MODULE_META[mod] ?? { color: 'secondary', icon: 'ri-file-list-line', bg: 'rgba(0,0,0,0.05)', border: '#94a3b8' }
}

const pageBannerColor = computed(() => ({
  summary:          'primary',
  alasan:           'info',
  'history-pasien': 'deep-purple',
})[activeTab.value] ?? 'primary')

// CSV export
const summaryHeaders = [
  { title: 'No. MR', key: 'no_mr' }, { title: 'Nama Pasien', key: 'nama_pasien' }, { title: 'Jaminan', key: 'jaminan' },
  { title: 'QC', key: 'qc' }, { title: 'Edukasi', key: 'edukasi' },
  { title: 'Batal Ranap', key: 'batal' }, { title: 'Up Selling', key: 'up' }, { title: 'Alasan', key: 'alasan' }, { title: 'Tanggal', key: 'tanggal' },
]
const alasanHeaders = [
  { title: 'Tanggal', key: 'tanggal' }, { title: 'No. Reg', key: 'no_reg' }, { title: 'Nama Pasien', key: 'nama_pasien' },
  { title: 'Alasan', key: 'alasan' }, { title: 'Jaminan', key: 'jaminan' }, { title: 'Catatan', key: 'catatan' }, { title: 'Petugas', key: 'petugas' },
]
const historyHeaders = [
  { title: 'No. MR', key: 'no_mr' }, { title: 'Nama Pasien', key: 'nama_pasien' }, { title: 'Jaminan', key: 'jaminan' }, { title: 'Tanggal', key: 'tanggal' },
]
const activeHeaders = computed(() => ({ summary: summaryHeaders, alasan: alasanHeaders, 'history-pasien': historyHeaders })[activeTab.value] ?? [])

function exportCSV() {
  const cols = activeHeaders.value
  const csv = [cols.map(h => h.title).join(','), ...filteredData.value.map(r => cols.map(h => `"${r[h.key] ?? ''}"`).join(','))].join('\n')
  const a = document.createElement('a'); a.href = URL.createObjectURL(new Blob(['\uFEFF' + csv], { type: 'text/csv' }))
  a.download = `view-${activeTab.value}-${new Date().toISOString().slice(0, 10)}.csv`; a.click()
}

let tabInitialized = false
onMounted(() => {
  if (!tabInitialized) {
    activeTab.value = auth.hasPermission('quality-control:view') ? 'summary' : 'history-pasien'
    tabInitialized = true
  }
  loadAll()
})

watch([dateFrom, dateTo], () => loadAll())

watch(() => auth.permissions, (perms, prev) => {
  if (perms?.length && perms !== prev) {
    if (!tabInitialized) {
      activeTab.value = auth.hasPermission('quality-control:view') ? 'summary' : 'history-pasien'
      tabInitialized = true
    }
    loadAll()
  }
})

// ── Detail dialog ─────────────────────────────────────────────────────────────
const showDetail    = ref(false)
const detailItem    = ref(null)
const detailType    = ref('summary')

function openDetail(item, type = activeTab.value) {
  detailItem.value = { ...item }
  detailType.value = type
  showDetail.value = true
}

// ── Pagination ────────────────────────────────────────────────────────────────
const { page: vdiPage, pageCount: vdiPageCount, paginated: vdiPaginated, setPage: vdiSetPage }
  = usePagination(filteredData, 10)

watch(activeTab, () => { vdiPage.value = 1 })
</script>

<template>
  <div>
    <!-- Header -->
    <PageHero
      icon="ri-table-line"
      badge="View Data Input"
      title="View Data Input"
      :subtitle="isKasir ? 'Data pasien real-time' : 'Summary semua data input per modul'"
      color-from="#0EA5E9"
      color-to="#0369A1"
      :pills="[
        { icon: 'ri-calendar-line', text: todayFormatted },
        { icon: 'ri-database-line', text: `${filteredData.length} data` },
      ]"
    >
      <template #actions>
        <VBtn
          v-if="!isKasir"
          color="white" variant="elevated" rounded="pill" size="small"
          style="color:#0369A1;font-weight:700"
          @click="exportCSV"
        >
          <VIcon icon="ri-download-line" size="15" class="me-1" />Export CSV
        </VBtn>
      </template>
    </PageHero>

    <!-- Summary Cards — klik untuk switch tab -->
    <SummaryCards
      v-if="!isKasir"
      v-model="activeTab"
      :cards="[
        { value: grandStats.alasan,       label: 'Alasan Kunjungan', color: 'info',        icon: 'ri-question-answer-line', filterValue: 'alasan' },
        { value: grandStats.edukasi,      label: 'Edukasi Lanjutan', color: 'warning',     icon: 'ri-book-open-line',       filterValue: 'history-pasien' },
        { value: grandStats.edukasiTransfer, label: 'Sudah Dapat Bed', color: 'success',   icon: 'ri-home-heart-line',      filterValue: 'history-pasien' },
        { value: grandStats.batal,        label: 'Batal Ranap',       color: 'error',       icon: 'ri-close-circle-line',    filterValue: 'history-pasien' },
        { value: grandStats.up,           label: 'Up Selling',        color: 'orange',      icon: 'ri-arrow-up-circle-line', filterValue: 'history-pasien' },
      ]"
    />
    <SummaryCards
      v-else
      :cards="[
        { value: batalData.length,                                                          label: 'Total',         color: 'primary', icon: 'ri-close-circle-line' },
        { value: batalData.filter(r => r.status_closing === 'Siap Closing').length,        label: 'Siap Closing',  color: 'success', icon: 'ri-checkbox-circle-line' },
        { value: batalData.filter(r => !r.status_closing).length,                          label: 'Belum Closing', color: 'warning', icon: 'ri-time-line' },
      ]"
    />

    <!-- Tabs + Filter -->
    <VCard elevation="0" border rounded="lg" class="mb-4">
      <!-- Tabs -->
      <VTabs v-model="activeTab" color="primary" show-arrows density="compact" class="vdi-tabs">
        <VTab v-for="t in tabs" :key="t.key" :value="t.key" class="vdi-tab">
          <VIcon :icon="t.icon" size="15" class="me-1" />
          {{ xs ? t.shortLabel : t.label }}
        </VTab>
      </VTabs>
      <VDivider />

      <!-- Penanda tab aktif -->
      <div class="vdi-page-banner" :class="`vdi-page-banner--${activeTab}`">
        <VIcon :icon="tabs.find(t => t.key === activeTab)?.icon ?? 'ri-table-line'" size="14" class="me-1" />
        <span>Anda sedang melihat: <strong>{{ tabs.find(t => t.key === activeTab)?.label ?? activeTab }}</strong></span>
        <VChip size="x-small" variant="tonal" :color="pageBannerColor" class="ms-2">{{ filteredData.length }} data</VChip>
      </div>
      <VDivider />

      <!-- Filter row -->
      <VCardText class="pa-3">
        <VRow dense align="center" class="g-2">
          <VCol cols="12" sm="4" md="4">
            <VTextField
              v-model="search" label="Cari..."
              prepend-inner-icon="ri-search-line" variant="outlined"
              density="compact" hide-details clearable rounded="lg"
            />
          </VCol>
          <VCol cols="6" sm="3" md="2">
            <VTextField v-model="dateFrom" label="Dari" type="date" variant="outlined" density="compact" hide-details rounded="lg" />
          </VCol>
          <VCol cols="6" sm="3" md="2">
            <VTextField v-model="dateTo" label="Sampai" type="date" variant="outlined" density="compact" hide-details rounded="lg" />
          </VCol>
          <!-- Filter alasan (hanya tab alasan) -->
          <VCol v-if="activeTab === 'alasan'" cols="12" sm="auto" md="3">
            <VSelect
              v-model="filterAlasan" :items="alasanOptions"
              item-title="title" item-value="value"
              label="Filter Alasan RS" variant="outlined" density="compact" hide-details rounded="lg"
            />
          </VCol>
          <VCol cols="auto">
            <VBtn size="small" variant="text" color="secondary"
              @click="search = ''; dateFrom = todayStr(); dateTo = todayStr(); filterAlasan = ''">
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

    <!-- ── List View ─────────────────────────────────────────────────────────── -->
    <VCard elevation="0" border rounded="lg" class="overflow-hidden">

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

        <!-- ══ HISTORY PASIEN — list pasien sederhana, klik untuk detail ══ -->
        <template v-if="activeTab === 'history-pasien'">
          <div
            v-for="(item, idx) in vdiPaginated"
            :key="item.no_mr ?? idx"
            class="vdi-row cursor-pointer"
            @click="openDetail(item, 'history-pasien')"
          >
            <VAvatar color="deep-purple" variant="tonal" size="38" rounded="md" class="flex-shrink-0">
              <span style="font-size:12px;font-weight:700">{{ item.nama_pasien?.charAt(0) ?? '?' }}</span>
            </VAvatar>

            <div class="flex-grow-1 min-width-0">
              <div class="d-flex align-center gap-2 flex-wrap mb-1">
                <span class="font-weight-semibold text-truncate" style="font-size:0.875rem;color:var(--qc-text)">
                  {{ item.nama_pasien }}
                </span>
                <VChip size="x-small" color="secondary" variant="tonal">{{ item.jaminan || '—' }}</VChip>
                <!-- Badge ringkas per modul yang ada -->
                <template v-for="mod in ['edukasi-awal','edukasi-lanjutan','sudah-dapat-bed','batal-ranap','up-selling']" :key="mod">
                  <VChip
                    v-if="item._events.some(e => e._module === mod)"
                    :color="moduleMeta(mod).color"
                    size="x-small" variant="tonal"
                  >
                    <VIcon :icon="moduleMeta(mod).icon" size="10" class="me-1" />
                    {{ { 'edukasi-awal': 'Edu Awal', 'edukasi-lanjutan': 'Edu Lanjut', 'sudah-dapat-bed': 'Dpt Bed', 'batal-ranap': 'Batal', 'up-selling': 'Up Sell' }[mod] }}
                    {{ item._events.filter(e => e._module === mod).length > 1 ? item._events.filter(e => e._module === mod).length + 'x' : '' }}
                  </VChip>
                </template>
              </div>
              <div class="d-flex align-center gap-2">
                <span class="text-caption" style="color:var(--qc-text-2)">{{ item.no_mr || item.no_reg || '—' }}</span>
                <span class="text-caption" style="color:var(--qc-text-2)">
                  <VIcon icon="ri-history-line" size="10" class="me-1" />{{ item._events.length }} aktivitas
                </span>
              </div>
            </div>

            <div class="text-end flex-shrink-0 vdi-right">
              <p class="text-caption mb-0" style="color:var(--qc-text-2)">{{ item.tanggal || '—' }}</p>
              <p class="text-caption mb-0" style="color:var(--qc-text-2);font-size:0.65rem">
                <VIcon icon="ri-arrow-right-s-line" size="12" />
              </p>
            </div>
          </div>
        </template>

        <!-- ══ SUMMARY & ALASAN — tampilan list biasa ══ -->
        <template v-else>
          <div
            v-for="(item, idx) in vdiPaginated"
            :key="item.id ?? idx"
            class="vdi-row cursor-pointer"
            @click="openDetail(item)"
          >
            <!-- Avatar -->
            <VAvatar color="primary" variant="tonal" size="38" rounded="md" class="flex-shrink-0">
              <span style="font-size:12px;font-weight:700">{{ item.nama_pasien?.charAt(0) ?? '?' }}</span>
            </VAvatar>

            <!-- Content -->
            <div class="flex-grow-1 min-width-0">
              <!-- Row 1: nama + chips -->
              <div class="d-flex align-center gap-2 flex-wrap mb-1">
                <span class="font-weight-semibold text-truncate" style="font-size:0.875rem;color:var(--qc-text)">
                  {{ item.nama_pasien }}
                </span>

                <!-- Summary: count chips per modul -->
                <template v-if="activeTab === 'summary'">
                  <VChip v-if="item.qc"     size="x-small" color="primary" variant="tonal">
                    <VIcon icon="ri-shield-check-line" size="9" class="me-1" />Edu {{ item.qc }}
                  </VChip>
                  <VChip v-if="item.edukasi" size="x-small" color="warning" variant="tonal">
                    <VIcon icon="ri-book-open-line" size="9" class="me-1" />Lanjutan {{ item.edukasi }}
                  </VChip>
                  <VChip v-if="item.batal"  size="x-small" color="error" variant="tonal">
                    <VIcon icon="ri-close-circle-line" size="9" class="me-1" />Batal {{ item.batal }}
                  </VChip>
                  <VChip v-if="item.up"     size="x-small" color="success" variant="tonal">
                    <VIcon icon="ri-arrow-up-circle-line" size="9" class="me-1" />Up {{ item.up }}
                  </VChip>
                </template>

                <!-- Alasan -->
                <template v-else-if="activeTab === 'alasan'">
                  <VChip v-if="item.alasan" :color="alasanColor(item.alasan)" size="x-small" variant="tonal">
                    <VIcon icon="ri-question-answer-line" size="10" class="me-1" />{{ item.alasan }}
                  </VChip>
                  <VChip v-if="item.jaminan" size="x-small" color="secondary" variant="tonal">{{ item.jaminan }}</VChip>
                </template>
              </div>

              <!-- Row 2: meta -->
              <div class="d-flex align-center gap-3 flex-wrap">
                <span class="text-caption" style="color:var(--qc-text-2)">{{ item.no_mr || item.no_reg || '—' }}</span>
                <span v-if="activeTab === 'summary' && item.jaminan" class="text-caption" style="color:var(--qc-text-2)">{{ item.jaminan }}</span>
                <span v-if="activeTab === 'alasan' && item.catatan" class="text-caption text-truncate" style="color:var(--qc-text-2);max-width:200px">
                  <VIcon icon="ri-chat-3-line" size="10" class="me-1" />"{{ item.catatan }}"
                </span>
              </div>
            </div>

            <!-- Right -->
            <div class="text-end flex-shrink-0 vdi-right">
              <p class="text-caption mb-0 font-weight-medium" style="color:var(--qc-text)">{{ item.petugas || '—' }}</p>
              <p class="text-caption mb-0" style="color:var(--qc-text-2)">{{ item.tanggal || item.tgl_daftar || '—' }}</p>
            </div>
          </div>
        </template>

        <PaginationBar
          :page="vdiPage" :page-count="vdiPageCount"
          :total="filteredData.length" :per-page="10"
          @update:page="vdiSetPage"
        />
      </div>
    </VCard>

    <!-- Detail Dialog -->
    <VdiDetailDialog v-model="showDetail" :item="detailItem" :type="detailType" />
  </div>
</template>

<style scoped>
/* ── List row (summary & alasan) ── */
.vdi-row {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 14px 16px;
  transition: background 0.12s;
  border-bottom: 1px solid var(--qc-border, rgba(0, 0, 0, 0.07));
}
.vdi-row:last-child { border-bottom: none; }
.vdi-row:hover {
  background: rgba(14, 165, 233, 0.04);
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
  .vdi-right { display: none; }
  .vdi-row   { padding: 12px 12px; }
}

/* ── Page banner ── */
.vdi-page-banner {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: 6px;
  padding: 7px 16px;
  font-size: 0.78rem;
  color: var(--qc-text-2, #64748b);
  background: rgba(14, 165, 233, 0.04);
  transition: background 0.2s;
}
.vdi-page-banner strong { color: var(--qc-text, #1e293b); }
.vdi-page-banner--summary        { background: rgba(14, 165, 233, 0.06); }
.vdi-page-banner--alasan         { background: rgba(14, 165, 233, 0.06); }
.vdi-page-banner--history-pasien { background: rgba(109, 40, 217, 0.05); }

/* Legend modul di history */
.vdi-history-legend {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: 8px;
}
.vdi-legend-pill {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  font-size: 0.68rem;
  color: var(--qc-text-2, #64748b);
}
.vdi-legend-dot {
  width: 8px; height: 8px;
  border-radius: 50%;
  flex-shrink: 0;
}

@media (max-width: 700px) {
  .vdi-history-legend { display: none; }
}

/* ── History Pasien ── */
.vdi-history-patient {
  border-bottom: 1px solid var(--qc-border, rgba(0,0,0,0.07));
}
.vdi-history-patient:last-child { border-bottom: none; }

.vdi-hp-header {
  display: flex;
  align-items: flex-start;
  gap: 12px;
  padding: 14px 16px 10px;
  background: rgba(109, 40, 217, 0.03);
  border-bottom: 1px solid rgba(109, 40, 217, 0.08);
}

.vdi-hp-timeline {
  padding: 0 16px 10px 52px;
}

/* Timeline row */
.vdi-tl-row {
  display: flex;
  align-items: flex-start;
  gap: 10px;
  padding: 8px 10px 8px 0;
  cursor: pointer;
  border-radius: 8px;
  transition: background 0.12s;
}
.vdi-tl-row:hover {
  background: var(--tl-bg, rgba(0,0,0,0.04));
}

/* Dot + vertical line */
.vdi-tl-dot-wrap {
  display: flex;
  flex-direction: column;
  align-items: center;
  flex-shrink: 0;
  width: 22px;
  padding-top: 2px;
}
.vdi-tl-dot {
  width: 22px; height: 22px;
  border-radius: 50%;
  display: flex; align-items: center; justify-content: center;
  flex-shrink: 0;
  box-shadow: 0 1px 4px rgba(0,0,0,0.15);
}
.vdi-tl-line {
  width: 2px;
  flex: 1;
  min-height: 12px;
  background: rgba(0,0,0,0.1);
  margin-top: 3px;
}

.vdi-tl-content {
  flex: 1;
  min-width: 0;
  padding-top: 1px;
}
</style>
