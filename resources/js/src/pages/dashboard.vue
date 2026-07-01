<script setup>
import { useDashboardStore } from '@/stores/useDashboardStore'
import { useAuthStore }      from '@/stores/useAuthStore'
import VueApexCharts          from 'vue3-apexcharts'

import DashboardAvgTable          from '@/views/qc-admission/dashboard/DashboardAvgTable.vue'
import DashboardMatrixTable       from '@/views/qc-admission/dashboard/DashboardMatrixTable.vue'
import DashboardEdukasiPerPetugas from '@/views/qc-admission/dashboard/DashboardEdukasiPerPetugas.vue'
import DashboardFunnelChart       from '@/views/qc-admission/dashboard/DashboardFunnelChart.vue'
import DashboardBarChart          from '@/views/qc-admission/dashboard/DashboardBarChart.vue'
import DashboardRecentQCTable     from '@/views/qc-admission/dashboard/DashboardRecentQCTable.vue'

const store = useDashboardStore()
const auth  = useAuthStore()

// ── Date filter ───────────────────────────────────────────────────────────────
const today   = new Date().toISOString().slice(0, 10)
const dateFrom = ref(today)
const dateTo   = ref(today)
const showDatePicker = ref(false)

// Quick ranges
const RANGES = [
  { label: 'Hari Ini',     days: 0  },
  { label: '7 Hari',       days: 6  },
  { label: '30 Hari',      days: 29 },
  { label: 'Bulan Ini',    days: -1 }, // special
  { label: 'Semua Data',   days: -2 }, // special
]

function applyRange(days) {
  const now  = new Date()
  const to   = now.toISOString().slice(0, 10)
  if (days === 0) {
    dateFrom.value = to
    dateTo.value   = to
  } else if (days === -1) {
    dateFrom.value = new Date(now.getFullYear(), now.getMonth(), 1).toISOString().slice(0, 10)
    dateTo.value   = to
  } else if (days === -2) {
    dateFrom.value = '2020-01-01'
    dateTo.value   = to
  } else {
    const from = new Date(now)
    from.setDate(now.getDate() - days)
    dateFrom.value = from.toISOString().slice(0, 10)
    dateTo.value   = to
  }
  doFetch()
  showDatePicker.value = false
}

async function doFetch() {
  await store.fetchDashboard(dateFrom.value, dateTo.value)
}

// ── Current time ──────────────────────────────────────────────────────────────
const now = ref(new Date())
let clockTimer
onMounted(() => {
  clockTimer = setInterval(() => { now.value = new Date() }, 1000)
  doFetch()
})
onUnmounted(() => clearInterval(clockTimer))

const formattedDate = computed(() =>
  now.value.toLocaleDateString('id-ID', { weekday:'long', day:'numeric', month:'long', year:'numeric' })
)
const formattedTime = computed(() =>
  now.value.toLocaleTimeString('id-ID', { hour:'2-digit', minute:'2-digit', second:'2-digit' })
)

// ── Date range label ──────────────────────────────────────────────────────────
const rangeLabelDisplay = computed(() => {
  if (dateFrom.value === dateTo.value) {
    return new Date(dateFrom.value).toLocaleDateString('id-ID', { day:'numeric', month:'long', year:'numeric' })
  }
  const fmtD = d => new Date(d).toLocaleDateString('id-ID', { day:'numeric', month:'short', year:'numeric' })
  return fmtD(dateFrom.value) + ' – ' + fmtD(dateTo.value)
})

// ── Stats ─────────────────────────────────────────────────────────────────────
const stats = computed(() => store.stats)

const kpiCards = computed(() => [
  {
    label: 'Jumlah Edukasi Pasien',
    value: (stats.value.jumlahEdukasiPasien ?? 0).toLocaleString('id-ID'),
    icon: 'ri-user-heart-line',
  },
  {
    label: 'Durasi Tunggu Edukasi (Menit)',
    value: stats.value.durasiTungguEdukasi ?? '0',
    icon: 'ri-timer-flash-line',
  },
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
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="flex-shrink:0">
              <rect x="3" y="4" width="18" height="18" rx="2"/><line x1="3" y1="9" x2="21" y2="9"/>
              <line x1="8" y1="2" x2="8" y2="5"/><line x1="16" y1="2" x2="16" y2="5"/>
            </svg>
            {{ rangeLabelDisplay }}
          </button>

          <Transition name="dp-fade">
            <div v-if="showDatePicker" class="db-date-panel" v-click-outside="() => { showDatePicker = false }">
              <!-- Quick ranges -->
              <div class="db-date-panel__quick">
                <button
                  v-for="r in RANGES" :key="r.label"
                  class="db-quick-btn"
                  @click="applyRange(r.days)"
                >
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
            <polyline points="23 4 23 10 17 10"/>
            <path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"/>
          </svg>
        </button>
      </div>
    </div>

    <!-- Subtitle bar -->
    <div class="db-subtitle-bar">
      <span class="db-subtitle-bar__time">
        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="flex-shrink:0">
          <circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>
        </svg>
        {{ formattedDate }} · {{ formattedTime }}
      </span>
      <span class="db-subtitle-bar__range">
        Periode: <strong>{{ rangeLabelDisplay }}</strong>
      </span>
      <span v-if="store.loading" class="db-subtitle-bar__loading">
        <VProgressCircular indeterminate size="14" width="2" color="#00B37E" class="me-1" />
        Memuat...
      </span>
    </div>

    <!-- ── Row 1: Avg Durasi | Matrix | Filter pills ─────────────────────── -->
    <div class="db-grid db-grid--row1 mb-4">
      <div class="db-grid__avg">
        <DashboardAvgTable :items="store.avgPerPetugas" :loading="store.loading" />
      </div>
      <div class="db-grid__matrix">
        <DashboardMatrixTable :items="store.matrix" :loading="store.loading" />
      </div>
      <div class="db-grid__filter">
        <!-- ══ Filter Panel ══════════════════════════════════════════════════ -->
        <div class="fp">

          <!-- BAGIAN 1: Cari Berdasarkan (date range) -->
          <p class="fp__title">Filter Tabel QC</p>

          <div class="fp__divider" />
          <!-- NamaPasien -->
          <div class="fp__qcf" :class="filterNamaPasien ? 'fp__qcf--on' : ''">
            <input v-model="filterNamaPasien" class="fp__qcf-in" placeholder="NamaPasien" />
            <button v-if="filterNamaPasien" class="fp__qcf-x" @click.stop="filterNamaPasien = ''">✕</button>
            <span v-else class="fp__qcf-ch">▾</span>
          </div>

          <!-- Petugas -->
          <div class="fp__qcf" :class="filterPetugas ? 'fp__qcf--on' : ''">
            <select v-model="filterPetugas" class="fp__qcf-in fp__qcf-sel">
              <option value="">Petugas</option>
              <option v-for="p in petugasOptions" :key="p" :value="p">{{ p }}</option>
            </select>
            <span class="fp__qcf-ch">▾</span>
          </div>

          <!-- NoMR -->
          <div class="fp__qcf" :class="filterNoMR ? 'fp__qcf--on' : ''">
            <input v-model="filterNoMR" class="fp__qcf-in" placeholder="NoMR" />
            <button v-if="filterNoMR" class="fp__qcf-x" @click.stop="filterNoMR = ''">✕</button>
            <span v-else class="fp__qcf-ch">▾</span>
          </div>

          <!-- Status -->
          <div class="fp__qcf" :class="filterStatus ? 'fp__qcf--on' : ''">
            <select v-model="filterStatus" class="fp__qcf-in fp__qcf-sel">
              <option value="">Status</option>
              <option value="Edukasi">Edukasi</option>
              <option value="Edukasi lanjutan">Edukasi lanjutan</option>
            </select>
            <span class="fp__qcf-ch">▾</span>
          </div>

          <button
            v-if="filterNamaPasien || filterPetugas || filterNoMR || filterStatus"
            class="fp__reset"
            @click="clearFilters"
          >✕ Reset Filter</button>

           <!-- Mini stats -->
          <div class="fp__stats">
            <div class="fp__stat">
              <span class="fp__stat-num">{{ (stats.totalEdukasi ?? 0).toLocaleString('id-ID') }}</span>
              <span class="fp__stat-lbl">Edukasi</span>
            </div>
            <div class="fp__stat">
              <span class="fp__stat-num">{{ (stats.totalEdukasiLanjutan ?? 0).toLocaleString('id-ID') }}</span>
              <span class="fp__stat-lbl">Edukasi Lanjutan</span>
            </div>
            <div class="fp__stat">
              <span class="fp__stat-num">{{ (stats.totalBatalRanap ?? 0).toLocaleString('id-ID') }}</span>
              <span class="fp__stat-lbl">Batal Ranap</span>
            </div>
          </div>

        </div>
      </div>
    </div>

    <!-- ── Row 2: Per Petugas | Funnel | Bar Chart ───────────────────────── -->
    <div class="db-grid db-grid--row2 mb-4">
      <div class="db-grid__petugas">
        <DashboardEdukasiPerPetugas :items="store.edukasiPerPetugas" :loading="store.loading" />
      </div>
      <div class="db-grid__funnel">
        <DashboardFunnelChart :items="store.perStatus" />
      </div>
      <div class="db-grid__bar">
        <DashboardBarChart :items="store.perKamar" />
      </div>
    </div>

    <!-- ── Row 3: Data Detail QC (full width) ───────────────────────────── -->
    <DashboardRecentQCTable :items="filteredRecentQC" :loading="store.loading" />

  </div>
</template>

<style>
/* Shared design-system card classes — global (no scoped) */
.ds-card {
  background: #FFFFFF !important;
  border: 1px solid #E1E7E5 !important;
  border-radius: 12px !important;
  box-shadow: 0 2px 8px rgba(16,24,22,0.08) !important;
  overflow: hidden;
}
.ds-card-header {
  display: flex;
  align-items: center;
  padding: 10px 16px;
  border-bottom: 1px solid #E1E7E5;
  background: #fff;
}
.ds-card-header--gradient {
  background: linear-gradient(90deg, #00C896, #009E6B) !important;
  border-bottom: none !important;
  flex-wrap: wrap;
  gap: 8px;
}
.ds-card-title { font-size: 0.85rem; color: #1A1F1E; }
.ds-card-title strong { color: #00B37E; }
.ds-card-title--white { font-size: 0.9rem; color: #fff; }
.ds-card-title--white strong { color: #fff; font-weight: 800; }
</style>

<style scoped>
/* ── Page background ───────────────────────────────────────────────────────── */
.db-page { background: #F4F7F6; min-height: 100vh; padding: 16px; }

/* ── Top bar ────────────────────────────────────────────────────────────────── */
.db-topbar {
  display: flex;
  align-items: center;
  gap: 12px;
  background: #fff;
  border: 1px solid #E1E7E5;
  border-radius: 12px;
  padding: 12px 20px;
  box-shadow: 0 1px 4px rgba(16,24,22,0.06);
  margin-bottom: 8px;
  flex-wrap: wrap;
}
.db-topbar__brand { display: flex; align-items: center; gap: 10px; }
.db-brand-pill {
  background: #00B37E;
  border-radius: 8px;
  padding: 5px 14px;
}
.db-brand-pill__dash { color: #fff; font-size: 1rem; font-weight: 800; }
.db-topbar__title { display: flex; flex-direction: column; line-height: 1.2; }
.db-topbar__title-main { font-size: 0.9rem; font-weight: 700; color: #1A1F1E; }
.db-topbar__title-sub  { font-size: 0.75rem; font-weight: 600; color: #00B37E; letter-spacing: 0.08em; }

/* KPI cards in topbar */
.db-kpi-row { display: flex; gap: 12px; flex-wrap: wrap; }
.db-kpi-card {
  background: #fff;
  border: 1.5px solid #00B37E;
  border-radius: 10px;
  padding: 8px 18px;
  text-align: center;
  min-width: 160px;
}
.db-kpi-card__label { font-size: 0.7rem; color: #5C6B67; margin-bottom: 2px; margin-top: 0; white-space: nowrap; }
.db-kpi-card__value { font-size: 1.5rem; font-weight: 800; color: #1A1F1E; margin: 0; line-height: 1; }

/* Date button */
.db-date-btn {
  display: flex; align-items: center; gap: 6px;
  background: #fff; border: 1.5px solid #00B37E; border-radius: 8px;
  padding: 6px 12px; color: #1A1F1E; font-size: 0.8rem; font-weight: 600;
  cursor: pointer; white-space: nowrap; transition: background 0.15s;
}
.db-date-btn:hover { background: #D7F5EA; }

/* Date panel dropdown */
.db-date-panel {
  position: absolute; top: calc(100% + 6px); right: 0;
  background: #fff; border: 1px solid #E1E7E5; border-radius: 12px;
  box-shadow: 0 8px 24px rgba(16,24,22,0.12);
  z-index: 100; min-width: 280px; padding: 12px;
}
.db-date-panel__quick { display: flex; flex-wrap: wrap; gap: 6px; margin-bottom: 10px; }
.db-quick-btn {
  padding: 4px 12px; border-radius: 20px; border: 1.5px solid #00B37E;
  background: #D7F5EA; color: #005C42; font-size: 0.78rem; font-weight: 600;
  cursor: pointer; transition: background 0.15s;
}
.db-quick-btn:hover { background: #00B37E; color: #fff; }
.db-date-panel__divider { border-top: 1px solid #E1E7E5; margin: 8px 0; }
.db-date-panel__custom { display: flex; flex-direction: column; gap: 8px; }
.db-date-field { display: flex; flex-direction: column; gap: 3px; }
.db-date-field label { font-size: 0.72rem; color: #5C6B67; font-weight: 600; }
.db-date-input {
  border: 1.5px solid #E1E7E5; border-radius: 8px; padding: 5px 10px;
  font-size: 0.82rem; color: #1A1F1E; outline: none;
  transition: border-color 0.15s;
}
.db-date-input:focus { border-color: #00B37E; }
.db-apply-btn {
  background: linear-gradient(90deg, #00C896, #009E6B);
  color: #fff; border: none; border-radius: 8px; padding: 7px 16px;
  font-size: 0.82rem; font-weight: 700; cursor: pointer; transition: opacity 0.15s;
}
.db-apply-btn:hover { opacity: 0.9; }

/* Refresh button */
.db-refresh-btn {
  width: 36px; height: 36px; border-radius: 50%;
  border: 1.5px solid #00B37E; background: #fff;
  color: #00B37E; cursor: pointer; display: flex; align-items: center; justify-content: center;
  transition: background 0.15s; flex-shrink: 0;
}
.db-refresh-btn:hover { background: #D7F5EA; }
.db-refresh-btn:disabled { opacity: 0.5; cursor: default; }

/* Subtitle bar */
.db-subtitle-bar {
  display: flex; align-items: center; gap: 16px;
  padding: 6px 4px; margin-bottom: 16px;
  flex-wrap: wrap;
}
.db-subtitle-bar__time {
  display: flex; align-items: center; gap: 5px;
  font-size: 0.78rem; color: #5C6B67;
}
.db-subtitle-bar__range { font-size: 0.78rem; color: #5C6B67; }
.db-subtitle-bar__range strong { color: #00B37E; }
.db-subtitle-bar__loading { display: flex; align-items: center; font-size: 0.78rem; color: #00B37E; }

/* ── Grid layouts ───────────────────────────────────────────────────────────── */
.db-grid { display: grid; gap: 16px; }

.db-grid--row1 {
  grid-template-columns: 1fr 2fr 260px;
  grid-template-areas: 'avg matrix filter';
}
.db-grid__avg    { grid-area: avg; }
.db-grid__matrix { grid-area: matrix; }
.db-grid__filter { grid-area: filter; }

.db-grid--row2 {
  grid-template-columns: 1fr 1fr 2fr;
  grid-template-areas: 'petugas funnel bar';
}
.db-grid__petugas { grid-area: petugas; }
.db-grid__funnel  { grid-area: funnel; }
.db-grid__bar     { grid-area: bar; }

@media (max-width: 1100px) {
  .db-grid--row1 { grid-template-columns: 1fr 1fr; grid-template-areas: 'avg matrix' 'filter filter'; }
  .db-grid--row2 { grid-template-columns: 1fr 1fr; grid-template-areas: 'petugas funnel' 'bar bar'; }
}
@media (max-width: 768px) {
  .db-topbar { padding: 10px 12px; }
  .db-kpi-row { display: none; }
  .db-grid--row1 { grid-template-columns: 1fr; grid-template-areas: 'avg' 'matrix' 'filter'; }
  .db-grid--row2 { grid-template-columns: 1fr; grid-template-areas: 'petugas' 'funnel' 'bar'; }
  .db-page { padding: 10px; }
}

/* ── Filter panel ───────────────────────────────────────────────────────────── */
.db-filter-panel {
  background: #fff;
  border: 1px solid #E1E7E5;
  border-radius: 12px;
  box-shadow: 0 2px 8px rgba(16,24,22,0.08);
  padding: 14px;
  height: 100%;
}
.db-filter-panel__title {
  font-size: 0.82rem; font-weight: 700; color: #1A1F1E;
  margin-bottom: 10px;
}
.db-filter-pills { display: flex; flex-direction: column; gap: 6px; }
.db-filter-pill {
  background: linear-gradient(90deg, #00C896, #009E6B);
  color: #fff; border: none; border-radius: 20px;
  padding: 6px 14px; font-size: 0.8rem; font-weight: 600;
  cursor: pointer; text-align: left; transition: opacity 0.15s;
  box-shadow: 0 2px 6px rgba(0,179,126,0.25);
}
.db-filter-pill:hover { opacity: 0.88; }
.db-filter-divider { border-top: 1px solid #E1E7E5; margin: 10px 0; }
.db-filter-inputs { display: flex; flex-direction: column; gap: 8px; }
.db-filter-field { display: flex; flex-direction: column; gap: 3px; }
.db-filter-field label { font-size: 0.72rem; font-weight: 600; color: #5C6B67; }
.db-filter-input {
  border: 1.5px solid #E1E7E5; border-radius: 8px; padding: 5px 8px;
  font-size: 0.8rem; color: #1A1F1E; outline: none; width: 100%;
}
.db-filter-input:focus { border-color: #00B37E; }
.db-filter-apply {
  background: linear-gradient(90deg, #00C896, #009E6B);
  color: #fff; border: none; border-radius: 8px; padding: 8px;
  font-size: 0.82rem; font-weight: 700; cursor: pointer;
  transition: opacity 0.15s; display: flex; align-items: center; justify-content: center; gap: 6px;
}
.db-filter-apply:hover { opacity: 0.9; }
.db-filter-apply:disabled { opacity: 0.6; cursor: default; }

.db-filter-stats { display: flex; gap: 8px; flex-wrap: wrap; }
.db-filter-stat {
  flex: 1; min-width: 60px;
  background: #D7F5EA; border-radius: 8px;
  padding: 6px 8px; text-align: center;
}
.db-filter-stat__num { display: block; font-size: 1rem; font-weight: 800; color: #005C42; }
.db-filter-stat__lbl { display: block; font-size: 0.65rem; color: #5C6B67; margin-top: 1px; }

/* ── Transitions ────────────────────────────────────────────────────────────── */
.dp-fade-enter-active, .dp-fade-leave-active { transition: opacity 0.15s, transform 0.15s; }
.dp-fade-enter-from, .dp-fade-leave-to { opacity: 0; transform: translateY(-6px); }

/* ── Spin animation ─────────────────────────────────────────────────────────── */
@keyframes spin { from { transform: rotate(0deg) } to { transform: rotate(360deg) } }

/* ════ Filter Panel (.fp) — persis sesuai gambar ════════════════════════════ */
.fp {
  background: #fff;
  border: 1px solid #E1E7E5;
  border-radius: 16px;
  box-shadow: 0 2px 12px rgba(16,24,22,0.08);
  padding: 16px 14px;
  max-height: calc(100vh - 200px);
  overflow-y: auto;
}

/* Scrollbar tipis */
.fp::-webkit-scrollbar { width: 4px; }
.fp::-webkit-scrollbar-track { background: transparent; }
.fp::-webkit-scrollbar-thumb { background: #E1E7E5; border-radius: 4px; }

.fp__title {
  font-size: 0.875rem;
  font-weight: 700;
  color: #1A1F1E;
  margin: 0 0 10px;
}

/* Quick range pills — full width, hijau solid, pill shape */
.fp__pills {
  display: flex;
  flex-direction: column;
  gap: 7px;
  margin-bottom: 12px;
}
.fp__pill {
  width: 100%;
  background: linear-gradient(90deg, #00C896 0%, #00A87C 100%);
  color: #fff;
  border: none;
  border-radius: 50px;
  padding: 10px 16px;
  font-size: 0.875rem;
  font-weight: 700;
  text-align: left;
  cursor: pointer;
  transition: opacity 0.15s, box-shadow 0.15s;
  box-shadow: 0 2px 8px rgba(0,180,126,0.22);
  letter-spacing: 0.01em;
}
.fp__pill:hover { opacity: 0.87; }
.fp__pill--on {
  background: linear-gradient(90deg, #009E6B 0%, #007A52 100%);
  box-shadow: 0 3px 10px rgba(0,100,70,0.3);
}

/* Date fields */
.fp__field { display: flex; flex-direction: column; gap: 4px; }
.fp__label { font-size: 0.78rem; font-weight: 600; color: #5C6B67; }
.fp__input {
  width: 100%;
  border: 1.5px solid #E1E7E5;
  border-radius: 10px;
  padding: 8px 10px;
  font-size: 0.85rem;
  color: #1A1F1E;
  background: #fff;
  outline: none;
  transition: border-color 0.15s;
  box-sizing: border-box;
}
.fp__input:focus { border-color: #00B37E; }

/* Tampilkan Data button */
.fp__apply {
  width: 100%;
  margin-top: 12px;
  background: linear-gradient(90deg, #00C896 0%, #009E6B 100%);
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
  box-shadow: 0 2px 10px rgba(0,180,126,0.28);
}
.fp__apply:hover { opacity: 0.9; }
.fp__apply:disabled { opacity: 0.6; cursor: default; }

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
  background: #D7F5EA;
  border-radius: 10px;
  padding: 7px 8px;
  text-align: center;
}
.fp__stat-num { display: block; font-size: 1rem; font-weight: 800; color: #005C42; line-height: 1.2; }
.fp__stat-lbl { display: block; font-size: 0.62rem; color: #5C6B67; margin-top: 2px; }

/* Divider */
.fp__divider { border: none; border-top: 1px solid #E1E7E5; margin: 14px 0; }

/* ── 4 QC column filter pills ──────────────────────────────────────────────── */
/* Struktur: background hijau gradient, pill shape, input transparan di dalam */
.fp__qcf {
  display: flex;
  align-items: center;
  background: linear-gradient(135deg, #00C896 0%, #009E6B 100%);
  border-radius: 50px;
  padding: 10px 14px 10px 16px;
  margin-bottom: 8px;
  box-shadow: 0 2px 8px rgba(0,180,126,0.2);
  transition: box-shadow 0.15s;
  position: relative;
}
.fp__qcf:hover { box-shadow: 0 3px 12px rgba(0,180,126,0.35); }
.fp__qcf--on {
  background: linear-gradient(135deg, #009E6B 0%, #007A52 100%);
  box-shadow: 0 3px 12px rgba(0,100,70,0.32);
}

/* Input/select di dalam pill */
.fp__qcf-in {
  flex: 1;
  background: transparent;
  border: none;
  outline: none;
  color: #1A1F1E;
  font-size: 0.875rem;
  font-weight: 600;
  min-width: 0;
  padding: 0;
  appearance: none;
  -webkit-appearance: none;
}
.fp__qcf-in::placeholder { color: rgba(26,31,30,0.8); }

.fp__qcf-sel { cursor: pointer; }
.fp__qcf-sel option { color: #1A1F1E; background: #fff; font-weight: 400; }

/* Chevron & X */
.fp__qcf-ch {
  font-size: 0.65rem;
  color: rgba(26,31,30,0.7);
  flex-shrink: 0;
  margin-left: 6px;
  pointer-events: none;
}
.fp__qcf-x {
  background: rgba(26,31,30,0.15);
  border: none;
  border-radius: 50%;
  width: 20px; height: 20px;
  display: flex; align-items: center; justify-content: center;
  font-size: 0.62rem;
  color: #1A1F1E;
  cursor: pointer;
  flex-shrink: 0;
  margin-left: 6px;
  transition: background 0.15s;
}
.fp__qcf-x:hover { background: rgba(26,31,30,0.28); }

/* Reset button */
.fp__reset {
  width: 100%;
  background: transparent;
  border: 1.5px solid #E1E7E5;
  border-radius: 50px;
  padding: 8px 12px;
  font-size: 0.78rem;
  font-weight: 600;
  color: #5C6B67;
  cursor: pointer;
  margin-top: 4px;
  transition: background 0.15s;
}
.fp__reset:hover { background: #F0F4F3; }
</style>
