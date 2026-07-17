<script setup>
import { useDashboardStore } from '@/stores/useDashboardStore'
import { usePegawaiStore } from '@/stores/usePegawaiStore'

import DashboardAvgTable from '@/views/qc-admission/dashboard/DashboardAvgTable.vue'
import DashboardMatrixTable from '@/views/qc-admission/dashboard/DashboardMatrixTable.vue'
import DashboardEdukasiPerPetugas from '@/views/qc-admission/dashboard/DashboardEdukasiPerPetugas.vue'
import DashboardFunnelChart from '@/views/qc-admission/dashboard/DashboardFunnelChart.vue'
import DashboardBarChart from '@/views/qc-admission/dashboard/DashboardBarChart.vue'
import DashboardRecentQCTable from '@/views/qc-admission/dashboard/DashboardRecentQCTable.vue'

const store = useDashboardStore()
const pegawaiStore = usePegawaiStore()

// ── Date filter ───────────────────────────────────────────────────────────────
function localDateStr(d = new Date()) {
  const p = n => String(n).padStart(2, '0')
  return `${d.getFullYear()}-${p(d.getMonth() + 1)}-${p(d.getDate())}`
}

const today = localDateStr()
const dateFrom = ref(today)
const dateTo = ref(today)
const activeRange = ref('Hari Ini')
const showDatePicker = ref(false)

const RANGES = [
  { label: 'Hari Ini', days: 0 },
  { label: '7 Hari', days: 6 },
  { label: '30 Hari', days: 29 },
  { label: 'Bulan Ini', days: -1 },
  { label: 'Semua Data', days: -2 },
]

function applyRange(r) {
  const days = typeof r === 'object' ? r.days : r
  const label = typeof r === 'object' ? r.label : ''
  activeRange.value = label
  const now = new Date()
  const to = localDateStr(now)
  if (days === 0) {
    dateFrom.value = to; dateTo.value = to
  } else if (days === -1) {
    dateFrom.value = localDateStr(new Date(now.getFullYear(), now.getMonth(), 1)); dateTo.value = to
  } else if (days === -2) {
    dateFrom.value = '2020-01-01'; dateTo.value = to
  } else {
    const from = new Date(now); from.setDate(now.getDate() - days)
    dateFrom.value = localDateStr(from); dateTo.value = to
  }
  doFetch()
  showDatePicker.value = false
}

async function doFetch() {
  await store.fetchDashboard(dateFrom.value, dateTo.value)
}

// ── Filter QC — checkbox multi-select ────────────────────────────────────────
const filterPasien = ref([])
const filterPetugas = ref([])
const filterStatus = ref([])
const searchPasien = ref('')
const searchPetugas = ref('')

const STATUS_OPTIONS = ['Edukasi', 'Edukasi lanjutan']

const pasienOptions = computed(() => {
  const seen = new Set()
  return (store.recentQC ?? [])
    .filter(r => {
      const k = r.no_mr || r.no_reg
      if (!k || seen.has(k)) return false
      seen.add(k); return true
    })
    .map(r => ({ label: `${r.nama_pasien || '—'} · ${r.no_mr || r.no_reg}`, value: r.no_mr || r.no_reg }))
    .sort((a, b) => a.label.localeCompare(b.label))
})

const petugasOptions = computed(() => {
  const seen = new Set()
  return (store.recentQC ?? [])
    .filter(r => r.petugas && !seen.has(r.petugas) && seen.add(r.petugas))
    .map(r => r.petugas).sort()
})

const filteredPasienOptions = computed(() => {
  if (!searchPasien.value.trim()) return pasienOptions.value
  const q = searchPasien.value.toLowerCase()
  return pasienOptions.value.filter(o => o.label.toLowerCase().includes(q))
})

const filteredPetugasOptions = computed(() => {
  if (!searchPetugas.value.trim()) return petugasOptions.value
  const q = searchPetugas.value.toLowerCase()
  return petugasOptions.value.filter(o => o.toLowerCase().includes(q))
})

function clearFilters() {
  filterPasien.value = []
  filterPetugas.value = []
  filterStatus.value = []
  searchPasien.value = ''
  searchPetugas.value = ''
}

const hasActiveFilter = computed(() =>
  filterPasien.value.length > 0 || filterPetugas.value.length > 0 || filterStatus.value.length > 0
)

const filteredRecentQC = computed(() => {
  let data = store.recentQC ?? []
  if (filterPasien.value.length)
    data = data.filter(r => filterPasien.value.includes(r.no_mr || r.no_reg))
  if (filterPetugas.value.length)
    data = data.filter(r => filterPetugas.value.includes(r.petugas))
  if (filterStatus.value.length)
    data = data.filter(r => filterStatus.value.includes(r.status))
  return data
})

// ── Derived chart data dari filteredRecentQC ────────────────────────────────
// Ketika filter aktif → semua komponen pakai data filtered
// Ketika tidak ada filter → pakai data asli dari store (sudah pre-computed di backend)

const isFiltered = computed(() => hasActiveFilter.value)

// avgPerPetugas: [{ petugas, avg_durasi_menit }]
const derivedAvgPerPetugas = computed(() => {
  if (!isFiltered.value) return store.avgPerPetugas
  const map = {}
  filteredRecentQC.value.forEach(r => {
    if (!r.petugas) return
    if (!map[r.petugas]) map[r.petugas] = { petugas: r.petugas, total: 0, count: 0 }
    if (r.durasi_tunggu) {
      const parts = r.durasi_tunggu.split(':').map(Number)
      const mnt = parts.length >= 2 ? parts[0] * 60 + parts[1] : 0
      map[r.petugas].total += mnt
      map[r.petugas].count++
    }
  })
  return Object.values(map).map(v => ({
    petugas: v.petugas,
    avg_durasi_menit: v.count > 0 ? Math.round(v.total / v.count) : 0,
  })).sort((a, b) => b.avg_durasi_menit - a.avg_durasi_menit)
})

// edukasiPerPetugas: [{ petugas, Edukasi, 'Edukasi lanjutan', total }]
const derivedEdukasiPerPetugas = computed(() => {
  if (!isFiltered.value) return store.edukasiPerPetugas
  const map = {}
  filteredRecentQC.value.forEach(r => {
    if (!r.petugas) return
    if (!map[r.petugas]) map[r.petugas] = { petugas: r.petugas, 'Edukasi': 0, 'Edukasi lanjutan': 0, total: 0 }
    const s = r.status || ''
    if (s === 'Edukasi' || s === 'Edukasi lanjutan') {
      map[r.petugas][s]++
      map[r.petugas].total++
    }
  })
  return Object.values(map).sort((a, b) => b.total - a.total)
})

// perStatus: [{ status, count }]
const derivedPerStatus = computed(() => {
  if (!isFiltered.value) return store.perStatus
  const map = {}
  filteredRecentQC.value.forEach(r => {
    const s = r.status || 'Unknown'
    map[s] = (map[s] ?? 0) + 1
  })
  return Object.entries(map).map(([status, count]) => ({ status, count }))
})

// perKamar: [{ edukasi_kamar, count }]
const derivedPerKamar = computed(() => {
  if (!isFiltered.value) return store.perKamar
  const map = {}
  filteredRecentQC.value.forEach(r => {
    const k = r.edukasi_kamar || '—'
    map[k] = (map[k] ?? 0) + 1
  })
  return Object.entries(map).map(([edukasi_kamar, count]) => ({ edukasi_kamar, count }))
    .sort((a, b) => b.count - a.count).slice(0, 10)
})

// matrix: [{ petugas, Edukasi, 'Edukasi lanjutan', total }] — sama dengan edukasiPerPetugas
const derivedMatrix = computed(() => {
  if (!isFiltered.value) return store.matrix
  return derivedEdukasiPerPetugas.value
})

// derived stats
const derivedStats = computed(() => {
  if (!isFiltered.value) return store.stats
  const data = filteredRecentQC.value
  const totalEdukasi = data.filter(r => r.status === 'Edukasi').length
  const totalLanjutan = data.filter(r => r.status === 'Edukasi lanjutan').length
  const durations = data.filter(r => r.durasi_tunggu).map(r => {
    const parts = r.durasi_tunggu.split(':').map(Number)
    return parts.length >= 2 ? parts[0] * 60 + parts[1] : 0
  })
  const avgDurasi = durations.length ? Math.round(durations.reduce((a, b) => a + b, 0) / durations.length) : 0
  return {
    ...store.stats,
    jumlahEdukasiPasien: data.length,
    durasiTungguEdukasi: String(avgDurasi),
    totalEdukasi,
    totalEdukasiLanjutan: totalLanjutan,
  }
})

// ── Clock ─────────────────────────────────────────────────────────────────────
const now = ref(new Date())
let clockTimer
onMounted(() => {
  clockTimer = setInterval(() => { now.value = new Date() }, 1000)
  doFetch()
  pegawaiStore.fetch()
})
onUnmounted(() => clearInterval(clockTimer))

const rangeLabelDisplay = computed(() => {
  const parseLocal = s => new Date(s + 'T00:00:00')
  if (dateFrom.value === dateTo.value)
    return parseLocal(dateFrom.value).toLocaleDateString('id-ID', { day: 'numeric', month: 'long', year: 'numeric' })
  const fmt = d => parseLocal(d).toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' })
  return fmt(dateFrom.value) + ' – ' + fmt(dateTo.value)
})

// ── Stats — pakai derived saat filter aktif ───────────────────────────────────
const stats = computed(() => derivedStats.value)

const kpiCards = computed(() => [
  { label: 'Jumlah Edukasi Pasien', value: (stats.value.jumlahEdukasiPasien ?? 0).toLocaleString('id-ID') },
  { label: 'Durasi Tunggu Edukasi (Menit)', value: stats.value.durasiTungguEdukasi ?? '0' },
])
</script>

<template>
  <div class="db-page">

    <!-- ── Top bar ──────────────────────────────────────────────────────────── -->
    <div class="db-topbar">
      <!-- Brand pill -->
      <div class="db-topbar__brand">
        <div class="db-brand-pill">
          <span class="db-brand-pill__dash">Dashboard</span>
        </div>
        <div class="db-topbar__title">
          <span class="db-topbar__title-main">Quality Control</span>
          <span class="db-topbar__title-sub">ADMISSION</span>
        </div>
      </div>

      <!-- Spacer -->
      <div style="flex:1" />

      <!-- KPI Cards -->
      <div class="db-kpi-row">
        <div v-for="kpi in kpiCards" :key="kpi.label" class="db-kpi-card">
          <p class="db-kpi-card__label">{{ kpi.label }}</p>
          <p class="db-kpi-card__value">{{ kpi.value }}</p>
        </div>
      </div>

      <!-- Controls -->
      <div class="d-flex align-center gap-2 ms-3">
        <!-- Date picker panel -->
        <div style="position:relative">
          <button class="db-date-btn" @click="showDatePicker = !showDatePicker">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
              style="flex-shrink:0">
              <rect x="3" y="4" width="18" height="18" rx="2" />
              <line x1="3" y1="9" x2="21" y2="9" />
              <line x1="8" y1="2" x2="8" y2="5" />
              <line x1="16" y1="2" x2="16" y2="5" />
            </svg>
            {{ rangeLabelDisplay }}
          </button>

          <Transition name="dp-fade">
            <div v-if="showDatePicker" class="db-date-panel" v-click-outside="() => { showDatePicker = false }">
              <!-- Quick ranges -->
              <div class="db-date-panel__quick">
                <button v-for="r in RANGES" :key="r.label" class="db-quick-btn" @click="applyRange(r.days)">
                  {{ r.label }}
                </button>
              </div>
              <div class="db-date-panel__divider" />
              <!-- Custom range -->
              <div class="db-date-panel__custom">
                <div class="db-date-field">
                  <label>Dari</label>
                  <input v-model="dateFrom" type="date" class="db-date-input" />
                </div>
                <div class="db-date-field">
                  <label>Sampai</label>
                  <input v-model="dateTo" type="date" class="db-date-input" />
                </div>
                <button class="db-apply-btn" @click="doFetch(); showDatePicker = false">Terapkan</button>
              </div>
            </div>
          </Transition>
        </div>

        <!-- Refresh -->
        <button class="db-refresh-btn" :disabled="store.loading" @click="doFetch">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"
            :style="store.loading ? 'animation:spin 1s linear infinite' : ''">
            <polyline points="23 4 23 10 17 10" />
            <path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10" />
          </svg>
        </button>
      </div>
    </div>

    <!-- ── Row 1: Avg Durasi | Matrix | Filter pills ─────────────────────── -->
    <div class="db-grid db-grid--row1 mb-4">
      <div class="db-grid__avg">
        <DashboardAvgTable :items="derivedAvgPerPetugas" :loading="store.loading" />
      </div>
      <div class="db-grid__matrix">
        <DashboardMatrixTable :items="derivedMatrix" :loading="store.loading" />
      </div>
      <div class="db-grid__filter">
        <!-- ══ Filter Panel ══════════════════════════════════════════════════ -->
        <div class="fp">

          <p class="fp__title">
            Filter Tabel QC
            <span v-if="hasActiveFilter" class="fp__active-badge">
              {{ filterPasien.length + filterPetugas.length + filterStatus.length }} aktif
            </span>
          </p>
          <div class="fp__divider" />

          <!-- ── Pasien + No MR ── -->
          <div class="fp__section">
            <div class="fp__section-head">
              <span class="fp__section-lbl">Pasien / No. MR</span>
              <span v-if="filterPasien.length" class="fp__badge">{{ filterPasien.length }}</span>
            </div>
            <input v-model="searchPasien" class="fp__search" placeholder="Cari pasien..." />
            <div class="fp__check-list">
              <label v-for="opt in filteredPasienOptions" :key="opt.value" class="fp__check-item">
                <input type="checkbox" :value="opt.value" v-model="filterPasien" class="fp__checkbox" />
                <span class="fp__check-label">{{ opt.label }}</span>
              </label>
              <p v-if="!filteredPasienOptions.length" class="fp__empty">Tidak ada data</p>
            </div>
          </div>

          <div class="fp__divider" />

          <!-- ── Petugas ── -->
          <div class="fp__section">
            <div class="fp__section-head">
              <span class="fp__section-lbl">Petugas</span>
              <span v-if="filterPetugas.length" class="fp__badge">{{ filterPetugas.length }}</span>
            </div>
            <input v-model="searchPetugas" class="fp__search" placeholder="Cari petugas..." />
            <div class="fp__check-list">
              <label v-for="p in filteredPetugasOptions" :key="p" class="fp__check-item">
                <input type="checkbox" :value="p" v-model="filterPetugas" class="fp__checkbox" />
                <span class="fp__check-label">{{ p }}</span>
              </label>
              <p v-if="!filteredPetugasOptions.length" class="fp__empty">Tidak ada data</p>
            </div>
          </div>

          <div class="fp__divider" />

          <!-- ── Status ── -->
          <div class="fp__section">
            <div class="fp__section-head">
              <span class="fp__section-lbl">Status</span>
              <span v-if="filterStatus.length" class="fp__badge">{{ filterStatus.length }}</span>
            </div>
            <div class="fp__check-list fp__check-list--compact">
              <label v-for="s in STATUS_OPTIONS" :key="s" class="fp__check-item">
                <input type="checkbox" :value="s" v-model="filterStatus" class="fp__checkbox" />
                <span class="fp__check-label">{{ s }}</span>
              </label>
            </div>
          </div>

          <button v-if="hasActiveFilter" class="fp__reset" @click="clearFilters">
            ✕ Reset Filter
          </button>

          <!-- Mini stats -->
          <div class="fp__divider" />
          <div class="fp__stats">
            <div class="fp__stat">
              <span class="fp__stat-num">{{ (derivedStats.totalEdukasi ?? 0).toLocaleString('id-ID') }}</span>
              <span class="fp__stat-lbl">Edukasi</span>
            </div>
            <div class="fp__stat">
              <span class="fp__stat-num">{{ (derivedStats.totalEdukasiLanjutan ?? 0).toLocaleString('id-ID') }}</span>
              <span class="fp__stat-lbl">Lanjutan</span>
            </div>
            <div class="fp__stat">
              <span class="fp__stat-num">{{ (derivedStats.totalBatalRanap ?? 0).toLocaleString('id-ID') }}</span>
              <span class="fp__stat-lbl">Batal Ranap</span>
            </div>
          </div>

        </div>
      </div>
    </div>

    <!-- ── Row 2: Per Petugas | Funnel | Bar Chart ───────────────────────── -->
    <div class="db-grid db-grid--row2 mb-4">
      <div class="db-grid__petugas">
        <DashboardEdukasiPerPetugas :items="derivedEdukasiPerPetugas" :loading="store.loading" />
      </div>
      <div class="db-grid__funnel">
        <DashboardFunnelChart :items="derivedPerStatus" />
      </div>
      <div class="db-grid__bar">
        <DashboardBarChart :items="derivedPerKamar" />
      </div>
    </div>

    <!-- ── Row 3: Data Detail QC (full width) ───────────────────────────── -->
    <DashboardRecentQCTable :items="filteredRecentQC" :loading="store.loading" />

  </div>
</template>

<style>
/* ── Shared ds-card — dark mode aware ─────────────────────────────────────── */
.ds-card {
  background: rgb(var(--v-theme-surface)) !important;
  border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity)) !important;
  border-radius: 12px !important;
  box-shadow: 0 2px 8px rgba(16, 24, 22, 0.08) !important;
  overflow: hidden;
}

.ds-card-header {
  display: flex;
  align-items: center;
  padding: 10px 16px;
  border-bottom: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  background: rgb(var(--v-theme-surface));
}

.ds-card-header--gradient {
  background: linear-gradient(90deg, rgb(var(--v-theme-primary)), rgb(var(--v-theme-primary-darken-1))) !important;
  border-bottom: none !important;
  flex-wrap: wrap;
  gap: 8px;
}

.ds-card-title {
  font-size: 0.85rem;
  color: rgb(var(--v-theme-on-surface));
}

.ds-card-title strong {
  color: rgb(var(--v-theme-primary));
}

.ds-card-title--white {
  font-size: 0.9rem;
  color: #fff;
}

.ds-card-title--white strong {
  color: #fff;
  font-weight: 800;
}
</style>

<style scoped>
/* ── Page background — dark mode aware ─────────────────────────────────────── */
.db-page {
  background: rgb(var(--v-theme-background));
  color: rgb(var(--v-theme-on-background));
  min-height: 100vh;
  padding: 16px;
}

/* ── Top bar ────────────────────────────────────────────────────────────────── */
.db-topbar {
  display: flex;
  align-items: center;
  gap: 12px;
  background: rgb(var(--v-theme-surface));
  border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  border-radius: 12px;
  padding: 12px 20px;
  box-shadow: 0 1px 4px rgba(16, 24, 22, 0.06);
  margin-bottom: 8px;
  flex-wrap: wrap;
}

.db-topbar__brand {
  display: flex;
  align-items: center;
  gap: 10px;
}

.db-brand-pill {
  background: rgb(var(--v-theme-primary));
  border-radius: 8px;
  padding: 5px 14px;
}

.db-brand-pill__dash {
  color: #fff;
  font-size: 1rem;
  font-weight: 800;
}

.db-topbar__title {
  display: flex;
  flex-direction: column;
  line-height: 1.2;
}

.db-topbar__title-main {
  font-size: 0.9rem;
  font-weight: 700;
  color: rgb(var(--v-theme-on-surface));
}

.db-topbar__title-sub {
  font-size: 0.75rem;
  font-weight: 600;
  color: rgb(var(--v-theme-primary));
  letter-spacing: 0.08em;
}

/* KPI cards */
.db-kpi-row {
  display: flex;
  gap: 12px;
  flex-wrap: wrap;
}

.db-kpi-card {
  background: rgb(var(--v-theme-surface));
  border: 1.5px solid rgb(var(--v-theme-primary));
  border-radius: 10px;
  padding: 8px 18px;
  text-align: center;
  min-width: 160px;
}

.db-kpi-card__label {
  font-size: 0.7rem;
  color: rgba(var(--v-theme-on-surface), 0.6);
  margin-bottom: 2px;
  margin-top: 0;
  white-space: nowrap;
}

.db-kpi-card__value {
  font-size: 1.5rem;
  font-weight: 800;
  color: rgb(var(--v-theme-on-surface));
  margin: 0;
  line-height: 1;
}

/* Date button */
.db-date-btn {
  display: flex;
  align-items: center;
  gap: 6px;
  background: rgb(var(--v-theme-surface));
  border: 1.5px solid rgb(var(--v-theme-primary));
  border-radius: 8px;
  padding: 6px 12px;
  color: rgb(var(--v-theme-on-surface));
  font-size: 0.8rem;
  font-weight: 600;
  cursor: pointer;
  white-space: nowrap;
  transition: background 0.15s;
}

.db-date-btn:hover {
  background: rgba(var(--v-theme-primary), 0.08);
}

/* Date panel */
.db-date-panel {
  position: absolute;
  top: calc(100% + 6px);
  right: 0;
  background: rgb(var(--v-theme-surface));
  border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  border-radius: 12px;
  box-shadow: 0 8px 24px rgba(16, 24, 22, 0.12);
  z-index: 100;
  min-width: 280px;
  padding: 12px;
}

.db-date-panel__quick {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
  margin-bottom: 10px;
}

.db-quick-btn {
  padding: 4px 12px;
  border-radius: 20px;
  border: 1.5px solid rgb(var(--v-theme-primary));
  background: rgba(var(--v-theme-primary), 0.1);
  color: rgb(var(--v-theme-primary));
  font-size: 0.78rem;
  font-weight: 600;
  cursor: pointer;
  transition: background 0.15s;
}

.db-quick-btn:hover {
  background: rgb(var(--v-theme-primary));
  color: #fff;
}

.db-date-panel__divider {
  border-top: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  margin: 8px 0;
}

.db-date-panel__custom {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.db-date-field {
  display: flex;
  flex-direction: column;
  gap: 3px;
}

.db-date-field label {
  font-size: 0.72rem;
  color: rgba(var(--v-theme-on-surface), 0.6);
  font-weight: 600;
}

.db-date-input {
  border: 1.5px solid rgba(var(--v-border-color), var(--v-border-opacity));
  border-radius: 8px;
  padding: 5px 10px;
  font-size: 0.82rem;
  color: rgb(var(--v-theme-on-surface));
  background: rgb(var(--v-theme-surface));
  outline: none;
  transition: border-color 0.15s;
}

.db-date-input:focus {
  border-color: rgb(var(--v-theme-primary));
}

.db-apply-btn {
  background: linear-gradient(90deg, rgb(var(--v-theme-primary)), rgb(var(--v-theme-primary-darken-1)));
  color: #fff;
  border: none;
  border-radius: 8px;
  padding: 7px 16px;
  font-size: 0.82rem;
  font-weight: 700;
  cursor: pointer;
  transition: opacity 0.15s;
}

.db-apply-btn:hover {
  opacity: 0.9;
}

/* Refresh button */
.db-refresh-btn {
  width: 36px;
  height: 36px;
  border-radius: 50%;
  border: 1.5px solid rgb(var(--v-theme-primary));
  background: rgb(var(--v-theme-surface));
  color: rgb(var(--v-theme-primary));
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: background 0.15s;
  flex-shrink: 0;
}

.db-refresh-btn:hover {
  background: rgba(var(--v-theme-primary), 0.08);
}

.db-refresh-btn:disabled {
  opacity: 0.5;
  cursor: default;
}

/* Subtitle bar */
.db-subtitle-bar {
  display: flex;
  align-items: center;
  gap: 16px;
  padding: 6px 4px;
  margin-bottom: 16px;
  flex-wrap: wrap;
}

.db-subtitle-bar__time {
  display: flex;
  align-items: center;
  gap: 5px;
  font-size: 0.78rem;
  color: rgba(var(--v-theme-on-surface), 0.6);
}

.db-subtitle-bar__range {
  font-size: 0.78rem;
  color: rgba(var(--v-theme-on-surface), 0.6);
}

.db-subtitle-bar__range strong {
  color: rgb(var(--v-theme-primary));
}

.db-subtitle-bar__loading {
  display: flex;
  align-items: center;
  font-size: 0.78rem;
  color: rgb(var(--v-theme-primary));
}

/* ── Grid layouts ───────────────────────────────────────────────────────────── */
.db-grid {
  display: grid;
  gap: 14px;
}

@media (min-width: 1025px) {
  .db-grid--row1 {
    grid-template-columns: minmax(0, 1fr) minmax(0, 2fr) 240px;
    grid-template-areas: 'avg matrix filter';
  }

  .db-grid--row2 {
    grid-template-columns: minmax(0, 1fr) minmax(0, 1fr) minmax(0, 2fr);
    grid-template-areas: 'petugas funnel bar';
  }

  .db-grid__avg {
    grid-area: avg;
  }

  .db-grid__matrix {
    grid-area: matrix;
    overflow-x: auto;
  }

  .db-grid__filter {
    grid-area: filter;
  }

  .db-grid__petugas {
    grid-area: petugas;
  }

  .db-grid__funnel {
    grid-area: funnel;
  }

  .db-grid__bar {
    grid-area: bar;
  }
}

@media (min-width: 769px) and (max-width: 1024px) {
  .db-grid--row1 {
    grid-template-columns: 1fr 1fr;
  }

  .db-grid--row2 {
    grid-template-columns: 1fr 1fr;
  }
}

@media (max-width: 768px) {
  .db-topbar {
    padding: 8px 12px;
    flex-wrap: wrap;
    gap: 6px;
  }

  .db-kpi-row {
    display: none;
  }

  .db-grid--row1,
  .db-grid--row2 {
    grid-template-columns: 1fr;
    gap: 10px;
  }

  .db-page {
    padding: 8px;
  }

  .db-date-btn {
    font-size: 0.73rem;
    padding: 5px 9px;
  }

  .db-topbar__brand {
    flex: 1;
    min-width: 0;
  }

  .db-date-panel {
    left: 0 !important;
    right: 0 !important;
    min-width: unset;
    position: fixed !important;
    bottom: 0;
    top: auto !important;
    border-radius: 16px 16px 0 0;
    padding: 16px;
  }

  .db-subtitle-bar {
    padding: 4px 0;
    gap: 8px;
    font-size: 0.72rem;
  }

  /* Filter panel jadi horizontal scroll di mobile */
  .fp {
    display: flex;
    flex-direction: column;
    padding: 12px;
    gap: 0;
  }

  .fp__check-list {
    max-height: 100px;
  }

  .fp__stats {
    flex-wrap: wrap;
  }
}

/* ── Filter panel (.fp) — dark mode aware ────────────────────────────────── */
.fp {
  background: rgb(var(--v-theme-surface));
  border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  border-radius: 16px;
  box-shadow: 0 2px 12px rgba(16, 24, 22, 0.08);
  padding: 16px 14px;
  max-height: calc(100vh - 200px);
  overflow-y: auto;
}

.fp::-webkit-scrollbar {
  width: 4px;
}

.fp::-webkit-scrollbar-track {
  background: transparent;
}

.fp::-webkit-scrollbar-thumb {
  background: rgba(var(--v-border-color), var(--v-border-opacity));
  border-radius: 4px;
}

.fp__title {
  font-size: 0.875rem;
  font-weight: 700;
  color: rgb(var(--v-theme-on-surface));
  margin: 0 0 10px;
}

.fp__divider {
  border: none;
  border-top: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  margin: 14px 0;
}

/* Filter pill buttons */
.fp__pills {
  display: flex;
  flex-direction: column;
  gap: 7px;
  margin-bottom: 12px;
}

.fp__pill {
  width: 100%;
  background: linear-gradient(90deg, rgb(var(--v-theme-primary)) 0%, rgb(var(--v-theme-primary-darken-1)) 100%);
  color: #fff;
  border: none;
  border-radius: 50px;
  padding: 10px 16px;
  font-size: 0.875rem;
  font-weight: 700;
  text-align: left;
  cursor: pointer;
  transition: opacity 0.15s, box-shadow 0.15s;
  box-shadow: 0 2px 8px rgba(var(--v-theme-primary), 0.22);
  letter-spacing: 0.01em;
}

.fp__pill:hover {
  opacity: 0.87;
}

.fp__pill--on {
  opacity: 0.85;
  box-shadow: 0 3px 10px rgba(var(--v-theme-primary), 0.3);
}

/* Date fields */
.fp__field {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.fp__label {
  font-size: 0.78rem;
  font-weight: 600;
  color: rgba(var(--v-theme-on-surface), 0.6);
}

.fp__input {
  width: 100%;
  border: 1.5px solid rgba(var(--v-border-color), var(--v-border-opacity));
  border-radius: 10px;
  padding: 8px 10px;
  font-size: 0.85rem;
  color: rgb(var(--v-theme-on-surface));
  background: rgb(var(--v-theme-surface));
  outline: none;
  transition: border-color 0.15s;
  box-sizing: border-box;
}

.fp__input:focus {
  border-color: rgb(var(--v-theme-primary));
}

/* Apply button */
.fp__apply {
  width: 100%;
  margin-top: 12px;
  background: linear-gradient(90deg, rgb(var(--v-theme-primary)) 0%, rgb(var(--v-theme-primary-darken-1)) 100%);
  color: #fff;
  border: none;
  border-radius: 10px;
  padding: 11px;
  font-size: 0.875rem;
  font-weight: 700;
  cursor: pointer;
  transition: opacity 0.15s;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 6px;
  box-shadow: 0 2px 10px rgba(var(--v-theme-primary), 0.28);
}

.fp__apply:hover {
  opacity: 0.9;
}

.fp__apply:disabled {
  opacity: 0.6;
  cursor: default;
}

/* Mini stats */
.fp__stats {
  display: flex;
  gap: 7px;
  flex-wrap: wrap;
  margin-top: 12px;
}

.fp__stat {
  flex: 1;
  min-width: 52px;
  background: rgba(var(--v-theme-primary), 0.1);
  border-radius: 10px;
  padding: 7px 8px;
  text-align: center;
}

.fp__stat-num {
  display: block;
  font-size: 1rem;
  font-weight: 800;
  color: rgb(var(--v-theme-primary));
  line-height: 1.2;
}

.fp__stat-lbl {
  display: block;
  font-size: 0.62rem;
  color: rgba(var(--v-theme-on-surface), 0.6);
  margin-top: 2px;
}

/* ── 4 QC filter pills ──────────────────────────────────────────────────────── */
.fp__qcf {
  display: flex;
  align-items: center;
  background: linear-gradient(135deg, rgb(var(--v-theme-primary)) 0%, rgb(var(--v-theme-primary-darken-1)) 100%);
  border-radius: 50px;
  padding: 9px 12px 9px 14px;
  margin-bottom: 8px;
  box-shadow: 0 2px 8px rgba(var(--v-theme-primary), 0.2);
  transition: box-shadow 0.15s;
  position: relative;
  cursor: pointer;
  gap: 8px;
}

.fp__qcf:hover {
  box-shadow: 0 3px 12px rgba(var(--v-theme-primary), 0.35);
}

.fp__qcf--on {
  opacity: 0.85;
  box-shadow: 0 3px 12px rgba(var(--v-theme-primary), 0.32);
}

.fp__qcf-icon {
  color: rgba(255, 255, 255, 0.8);
  flex-shrink: 0;
  display: flex;
  align-items: center;
  pointer-events: none;
}

.fp__qcf-in {
  flex: 1;
  background: transparent;
  border: none;
  outline: none;
  color: #fff;
  font-size: 0.875rem;
  font-weight: 600;
  min-width: 0;
  padding: 0;
  cursor: pointer;
}

.fp__qcf-in::placeholder {
  color: rgba(255, 255, 255, 0.75);
}

.fp__qcf--sel-wrap {
  cursor: pointer;
}

.fp__qcf--sel-wrap .fp__qcf-in {
  cursor: pointer;
  position: relative;
  z-index: 1;
}

.fp__qcf-ch {
  font-size: 0.65rem;
  color: rgba(255, 255, 255, 0.7);
  flex-shrink: 0;
  pointer-events: none;
}

.fp__qcf-x {
  background: rgba(255, 255, 255, 0.2);
  border: none;
  border-radius: 50%;
  width: 20px;
  height: 20px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 0.62rem;
  color: #fff;
  cursor: pointer;
  flex-shrink: 0;
  transition: background 0.15s;
  z-index: 2;
  position: relative;
}

.fp__qcf-x:hover {
  background: rgba(255, 255, 255, 0.35);
}

/* Status label row */
.fp__qcf-label {
  display: flex;
  align-items: center;
  gap: 6px;
  margin-bottom: 6px;
  padding: 0 2px;
}

.fp__qcf-label span:last-child {
  font-size: 0.78rem;
  font-weight: 600;
  color: rgba(var(--v-theme-on-surface), 0.7);
}

/* Status quick chips */
.fp__status-chip {
  padding: 4px 10px;
  border-radius: 20px;
  border: 1.5px solid rgba(var(--v-theme-primary), 0.3);
  background: rgba(var(--v-theme-primary), 0.08);
  color: rgb(var(--v-theme-primary));
  font-size: 0.72rem;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.15s;
  white-space: nowrap;
}

.fp__status-chip:hover {
  background: rgba(var(--v-theme-primary), 0.15);
}

.fp__status-chip--on {
  background: rgb(var(--v-theme-primary)) !important;
  border-color: rgb(var(--v-theme-primary)) !important;
  color: #fff !important;
}

/* Reset button */
.fp__reset {
  width: 100%;
  background: transparent;
  border: 1.5px solid rgba(var(--v-border-color), var(--v-border-opacity));
  border-radius: 10px;
  padding: 8px;
  font-size: 0.82rem;
  font-weight: 600;
  color: rgba(var(--v-theme-on-surface), 0.6);
  cursor: pointer;
  margin-top: 8px;
  transition: all 0.15s;
}

.fp__reset:hover {
  border-color: rgb(var(--v-theme-error));
  color: rgb(var(--v-theme-error));
}

/* ── Checkbox filter styles ── */
.fp__title {
  font-size: 0.875rem;
  font-weight: 700;
  color: rgb(var(--v-theme-on-surface));
  margin: 0 0 10px;
  display: flex;
  align-items: center;
  gap: 8px;
}

.fp__active-badge {
  font-size: 0.65rem;
  font-weight: 700;
  padding: 2px 7px;
  border-radius: 20px;
  background: rgb(var(--v-theme-primary));
  color: #fff;
}

.fp__section {
  margin-bottom: 6px;
}

.fp__section-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 6px;
}

.fp__section-lbl {
  font-size: 0.72rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.06em;
  color: rgba(var(--v-theme-on-surface), 0.5);
}

.fp__badge {
  font-size: 0.62rem;
  font-weight: 800;
  padding: 1px 6px;
  border-radius: 10px;
  background: rgb(var(--v-theme-primary));
  color: #fff;
}

.fp__search {
  width: 100%;
  border: 1.5px solid rgba(var(--v-border-color), var(--v-border-opacity));
  border-radius: 8px;
  padding: 5px 9px;
  font-size: 0.78rem;
  color: rgb(var(--v-theme-on-surface));
  background: rgb(var(--v-theme-surface));
  outline: none;
  margin-bottom: 6px;
  box-sizing: border-box;
  transition: border-color 0.15s;
}

.fp__search:focus {
  border-color: rgb(var(--v-theme-primary));
}

.fp__search::placeholder {
  color: rgba(var(--v-theme-on-surface), 0.35);
}

.fp__check-list {
  max-height: 130px;
  overflow-y: auto;
  display: flex;
  flex-direction: column;
  gap: 1px;
  scrollbar-width: thin;
}

.fp__check-list--compact {
  max-height: 80px;
}

.fp__check-list::-webkit-scrollbar {
  width: 3px;
}

.fp__check-list::-webkit-scrollbar-thumb {
  background: rgba(var(--v-theme-primary), 0.3);
  border-radius: 3px;
}

.fp__check-item {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 5px 6px;
  border-radius: 6px;
  cursor: pointer;
  transition: background 0.12s;
}

.fp__check-item:hover {
  background: rgba(var(--v-theme-primary), 0.08);
}

.fp__checkbox {
  width: 15px;
  height: 15px;
  flex-shrink: 0;
  cursor: pointer;
  accent-color: rgb(var(--v-theme-primary));
}

.fp__check-label {
  font-size: 0.8rem;
  color: rgba(var(--v-theme-on-surface), 0.82);
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
  flex: 1;
  min-width: 0;
}

.fp__empty {
  font-size: 0.75rem;
  color: rgba(var(--v-theme-on-surface), 0.4);
  text-align: center;
  padding: 8px 0;
  margin: 0;
}

.db-filter-panel {
  background: rgb(var(--v-theme-surface));
  border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  border-radius: 12px;
  box-shadow: 0 2px 8px rgba(16, 24, 22, 0.08);
  padding: 14px;
  height: 100%;
}

.db-filter-stat {
  flex: 1;
  min-width: 60px;
  background: rgba(var(--v-theme-primary), 0.1);
  border-radius: 8px;
  padding: 6px 8px;
  text-align: center;
}

.db-filter-stat__num {
  display: block;
  font-size: 1rem;
  font-weight: 800;
  color: rgb(var(--v-theme-primary));
}

.db-filter-stat__lbl {
  display: block;
  font-size: 0.65rem;
  color: rgba(var(--v-theme-on-surface), 0.6);
  margin-top: 1px;
}

/* Transitions & spin */
.dp-fade-enter-active,
.dp-fade-leave-active {
  transition: opacity 0.15s, transform 0.15s;
}

.dp-fade-enter-from,
.dp-fade-leave-to {
  opacity: 0;
  transform: translateY(-6px);
}

@keyframes spin {
  from {
    transform: rotate(0deg)
  }

  to {
    transform: rotate(360deg)
  }
}
</style>
