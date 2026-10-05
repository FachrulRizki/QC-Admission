<script setup>
import axios from 'axios'
/**
 * VdiDetailDialog — modal detail universal untuk semua tab di View Data Input
 */
const props = defineProps({
  modelValue: { type: Boolean, default: false },
  item:       { type: Object,  default: null   },
  type:       { type: String,  default: 'summary' },
})
const emit = defineEmits(['update:modelValue'])
function close() { emit('update:modelValue', false) }

// ── Inner tab untuk history-pasien & edukasi mode ────────────────────────────
const innerTab = ref('semua')

// ── State untuk history-pasien fetch dari API ─────────────────────────────────
const loadingHistory  = ref(false)
const historyEvents   = ref([])  // data dari API /history-pasien/{no_mr}
const historyPasien   = ref(null) // info pasien dari API

// Saat dialog dibuka, fetch data lengkap jika tipe history-pasien
watch(() => props.modelValue, async (v) => {
  if (!v) return
  innerTab.value = props.type === 'history-pasien' ? 'semua' : 'riwayat'

  if (props.type === 'history-pasien' && props.item) {
    await fetchHistoryPasien(props.item.no_mr || props.item.no_reg)
  }
})

async function fetchHistoryPasien(noMr) {
  if (!noMr) return
  loadingHistory.value = true
  historyEvents.value  = []
  try {
    const { data } = await axios.get(`/api/history-pasien/${encodeURIComponent(noMr)}`)
    // API mengembalikan events terurut terlama → terbaru
    // Kita balik untuk tampilan terbaru di atas
    historyEvents.value = [...(data.events ?? [])].reverse()
    historyPasien.value = data.pasien ?? null
  } catch (e) {
    // Fallback ke data lokal jika API gagal
    historyEvents.value = props.item?._events ? [...props.item._events] : []
  } finally {
    loadingHistory.value = false
  }
}

// ── Helper tanggal untuk sort ─────────────────────────────────────────────────
function parseTgl(r) {
  return r.tanggal || r.tgl_daftar || r.created_at || ''
}

// ── allEvents: gunakan data dari API jika sudah ada, fallback ke props._events
const allEvents = computed(() => {
  if (props.type === 'history-pasien') {
    return historyEvents.value.length ? historyEvents.value : (props.item?._events ?? [])
  }
  if (!props.item?._events) return []
  return [...props.item._events].sort((a, b) => parseTgl(b).localeCompare(parseTgl(a)))
})

// Info pasien — gabungan dari API dan props
const pasienInfo = computed(() => ({
  no_mr:       historyPasien.value?.no_mr       ?? props.item?.no_mr       ?? props.item?.no_reg ?? '—',
  jaminan:     historyPasien.value?.jaminan      ?? props.item?.jaminan     ?? '—',
  nama_pasien: historyPasien.value?.nama_pasien  ?? props.item?.nama_pasien ?? '—',
}))

const evEdukasiAwal = computed(() =>
  allEvents.value.filter(e => e._module === 'edukasi-awal')
)
const evEdukasiLanjutan = computed(() =>
  allEvents.value.filter(e => e._module === 'edukasi-lanjutan' || e._module === 'sudah-dapat-bed')
)
const evBatalRanap = computed(() =>
  allEvents.value.filter(e => e._module === 'batal-ranap')
)
const evUpSelling = computed(() =>
  allEvents.value.filter(e => e._module === 'up-selling')
)

// Tab counts untuk badge
const tabCounts = computed(() => ({
  semua:              allEvents.value.length,
  'edukasi-awal':     evEdukasiAwal.value.length,
  'edukasi-lanjutan': evEdukasiLanjutan.value.length,
  'batal-ranap':      evBatalRanap.value.length,
  'up-selling':       evUpSelling.value.length,
}))

// Data yang tampil sesuai inner tab
const activeEvents = computed(() => ({
  semua:              allEvents.value,
  'edukasi-awal':     evEdukasiAwal.value,
  'edukasi-lanjutan': evEdukasiLanjutan.value,
  'batal-ranap':      evBatalRanap.value,
  'up-selling':       evUpSelling.value,
})[innerTab.value] ?? allEvents.value)

// ── Edukasi mode (tipe lama, masih dipakai dari tab summary/alasan) ───────────
const allEdukasiTimeline = computed(() => {
  if (!props.item) return []
  const awal = (props.item.qc_records || []).map(r => ({ ...r, _type: 'awal' }))
  const lanjutan = (props.item.edukasi_records || []).map(r => ({ ...r, _type: 'lanjutan' }))
  return [...awal, ...lanjutan].sort((a, b) => {
    const da = a.tanggal || a.created_at || ''
    const db = b.tanggal || b.created_at || ''
    return db.localeCompare(da)
  })
})

// ── Metadata per type ────────────────────────────────────────────────────────
const META = {
  'summary':          { label: 'Summary Pasien',    icon: 'ri-bar-chart-box-line',   grad: ['#6366f1','#818cf8'] },
  'alasan':           { label: 'Alasan Kunjungan',  icon: 'ri-question-answer-line', grad: ['#0369A1','#0EA5E9'] },
  'history-pasien':   { label: 'History Pasien',    icon: 'ri-time-line',            grad: ['#7c3aed','#a78bfa'] },
  'edukasi-pasien':   { label: 'Edukasi Pasien',    icon: 'ri-user-heart-line',      grad: ['#7c3aed','#a78bfa'] },
  'sudah-dapat-bed':  { label: 'Sudah Dapat Bed',   icon: 'ri-home-heart-line',      grad: ['#059669','#34d399'] },
  'quality-control':  { label: 'Edukasi Awal',      icon: 'ri-shield-check-line',    grad: ['#7c3aed','#a78bfa'] },
  'edukasi-awal':     { label: 'Edukasi Awal',      icon: 'ri-shield-check-line',    grad: ['#7c3aed','#a78bfa'] },
  'edukasi-lanjutan': { label: 'Edukasi Lanjutan',  icon: 'ri-book-open-line',       grad: ['#059669','#34d399'] },
  'batal-ranap':      { label: 'Batal Ranap',        icon: 'ri-close-circle-line',    grad: ['#dc2626','#f87171'] },
  'up-selling':       { label: 'Up Selling',         icon: 'ri-arrow-up-circle-line', grad: ['#d97706','#fbbf24'] },
}
const meta = computed(() => META[props.type] ?? META['summary'])

const MODULE_META = {
  'edukasi-awal':     { color: 'primary',  icon: 'ri-shield-check-line',    border: '#6366f1', label: 'Edukasi Awal' },
  'edukasi-lanjutan': { color: 'warning',  icon: 'ri-book-open-line',       border: '#f59e0b', label: 'Edukasi Lanjutan' },
  'sudah-dapat-bed':  { color: 'success',  icon: 'ri-home-heart-line',      border: '#22c55e', label: 'Sudah Dapat Bed' },
  'batal-ranap':      { color: 'error',    icon: 'ri-close-circle-line',    border: '#ef4444', label: 'Batal Ranap' },
  'up-selling':       { color: 'orange',   icon: 'ri-arrow-up-circle-line', border: '#f97316', label: 'Up Selling' },
}
function modMeta(mod) {
  return MODULE_META[mod] ?? { color: 'secondary', icon: 'ri-file-list-line', border: '#94a3b8', label: mod }
}

// ── Computed total sesi untuk edukasi-pasien / sudah-dapat-bed ───────────────
const totalSesi = computed(() => {
  if (!props.item) return 0
  return (props.item.sesi_edukasi_awal || 0) + (props.item.sesi_edukasi_lanjutan || 0)
})

const isEdukasiMode = computed(() =>
  props.type === 'edukasi-pasien' || props.type === 'sudah-dapat-bed'
)

// ── Color helpers ────────────────────────────────────────────────────────────
function alasanColor(a) {
  return ({ 'Pelayanan':'primary','Kelengkapan Alat & Dokter':'warning','Teman/Kerabat':'info','Rujukan':'success','Marketing':'secondary','Sosial Media':'info' })[a] ?? 'secondary'
}
function statusColor(s) {
  return ({ 'Edukasi':'success','Edukasi lanjutan':'warning','Bedah':'success','Non Bedah':'info','Selesai':'success','Menunggu':'warning','Berhasil':'success','Tidak Berhasil':'error','Pending':'warning' })[s] ?? 'secondary'
}
function closingColor(s) {
  return s === 'Siap Closing' ? 'success' : s === 'Belum Siap Closing' ? 'error' : 'secondary'
}
function ketColor(k) {
  return k === 'Sudah Masuk Kamar' ? 'success' : k === 'Belum Diantar' ? 'orange' : 'info'
}
function fmtDate(d) {
  if (!d) return '—'
  try { return new Date(d).toLocaleString('id-ID', { day:'2-digit', month:'short', year:'numeric', hour:'2-digit', minute:'2-digit' }) }
  catch { return d }
}
</script>

<template>
  <VDialog :model-value="modelValue" max-width="560" @update:model-value="close">
    <VCard v-if="item" rounded="xl" class="vdd overflow-hidden">

      <!-- ── Banner ──────────────────────────────────────────────────────── -->
      <div class="vdd-banner" :style="`background: linear-gradient(135deg, ${meta.grad[0]} 0%, ${meta.grad[1]} 100%)`">
        <div class="vdd-blob vdd-blob--1" /><div class="vdd-blob vdd-blob--2" />
        <div class="vdd-bi">
          <!-- Avatar -->
          <div class="vdd-av">{{ item.nama_pasien?.charAt(0) ?? '?' }}</div>
          <!-- Info -->
          <div class="vdd-info">
            <p class="vdd-badge">
              <VIcon :icon="meta.icon" size="10" class="me-1" />{{ meta.label }}
            </p>
            <h2 class="vdd-name">{{ item.nama_pasien || '—' }}</h2>
            <p class="vdd-sub">
              {{ item.no_mr || item.no_reg || '—' }}
              <template v-if="item.jaminan"> · {{ item.jaminan }}</template>
              <!-- Total sesi badge -->
              <template v-if="isEdukasiMode && totalSesi">
                &nbsp;·&nbsp;<VIcon icon="ri-repeat-line" size="10" />{{ totalSesi }} sesi
              </template>
            </p>
          </div>
          <!-- Close -->
          <button class="vdd-close" @click="close">
            <VIcon icon="ri-close-line" size="16" />
          </button>
        </div>

        <!-- Sesi counter pills (hanya untuk edukasi mode) -->
        <div v-if="isEdukasiMode" class="vdd-sesi-pills">
          <div v-if="item.sesi_edukasi_lanjutan" class="vdd-sesi-pill vdd-sesi-pill--lanjutan">
            <VIcon icon="ri-book-open-line" size="12" />
            <span>{{ item.sesi_edukasi_lanjutan }}</span>
            <small>Edukasi Lanjutan</small>
          </div>
          <div class="vdd-sesi-pill vdd-sesi-pill--total">
            <VIcon icon="ri-repeat-line" size="12" />
            <span>{{ totalSesi }}</span>
            <small>Total Sesi</small>
          </div>
        </div>
      </div>

      <!-- ── Scrollable body ─────────────────────────────────────────────── -->
      <div class="vdd-body">

        <!-- ══════════════ HISTORY PASIEN — inner tabs per modul ════════════ -->
        <template v-if="type === 'history-pasien'">
          <!-- Info pasien singkat — pakai data dari API (lebih lengkap) -->
          <div class="vdd-patient-meta mb-3">
            <div class="vdd-pm-item">
              <span class="vdd-lbl">No. MR</span>
              <span class="vdd-val mono">{{ pasienInfo.no_mr }}</span>
            </div>
            <div class="vdd-pm-item">
              <span class="vdd-lbl">Jaminan</span>
              <span class="vdd-val">{{ pasienInfo.jaminan }}</span>
            </div>
            <div class="vdd-pm-item">
              <span class="vdd-lbl">Total Aktivitas</span>
              <span class="vdd-val font-weight-bold">
                <VProgressCircular v-if="loadingHistory" size="14" width="2" indeterminate color="deep-purple" class="me-1" />
                <template v-else>{{ allEvents.length }}×</template>
              </span>
            </div>
          </div>

          <!-- Inner tab bar — DIPINDAH KE SINI (dalam body tapi overflow terpisah) -->
          <div class="vdd-tab-bar-wrap">
            <div class="vdd-tab-bar">
            <button
              class="vdd-tab-btn"
              :class="{ 'vdd-tab-btn--active': innerTab === 'semua' }"
              @click="innerTab = 'semua'"
            >
              <VIcon icon="ri-list-check" size="13" class="me-1" />Semua
              <span v-if="tabCounts.semua" class="vdd-tab-badge">{{ tabCounts.semua }}</span>
            </button>
            <button
              v-if="tabCounts['edukasi-awal']"
              class="vdd-tab-btn"
              :class="{ 'vdd-tab-btn--active': innerTab === 'edukasi-awal' }"
              @click="innerTab = 'edukasi-awal'"
            >
              <VIcon icon="ri-shield-check-line" size="13" class="me-1" />Edukasi Awal
              <span class="vdd-tab-badge vdd-tab-badge--primary">{{ tabCounts['edukasi-awal'] }}</span>
            </button>
            <button
              v-if="tabCounts['edukasi-lanjutan']"
              class="vdd-tab-btn"
              :class="{ 'vdd-tab-btn--active': innerTab === 'edukasi-lanjutan' }"
              @click="innerTab = 'edukasi-lanjutan'"
            >
              <VIcon icon="ri-book-open-line" size="13" class="me-1" />Edukasi Lanjutan
              <span class="vdd-tab-badge vdd-tab-badge--warning">{{ tabCounts['edukasi-lanjutan'] }}</span>
            </button>
            <button
              v-if="tabCounts['batal-ranap']"
              class="vdd-tab-btn"
              :class="{ 'vdd-tab-btn--active': innerTab === 'batal-ranap' }"
              @click="innerTab = 'batal-ranap'"
            >
              <VIcon icon="ri-close-circle-line" size="13" class="me-1" />Batal Ranap
              <span class="vdd-tab-badge vdd-tab-badge--error">{{ tabCounts['batal-ranap'] }}</span>
            </button>
            <button
              v-if="tabCounts['up-selling']"
              class="vdd-tab-btn"
              :class="{ 'vdd-tab-btn--active': innerTab === 'up-selling' }"
              @click="innerTab = 'up-selling'"
            >
              <VIcon icon="ri-arrow-up-circle-line" size="13" class="me-1" />Up Selling
              <span class="vdd-tab-badge vdd-tab-badge--orange">{{ tabCounts['up-selling'] }}</span>
            </button>
          </div>
          </div><!-- /vdd-tab-bar-wrap -->

          <!-- Timeline events -->
          <div v-if="loadingHistory" class="text-center py-8">
            <VProgressCircular indeterminate color="deep-purple" size="28" />
            <p class="text-caption mt-2" style="color:var(--qc-text-2)">Memuat semua riwayat dari database...</p>
          </div>
          <div v-else-if="!activeEvents.length" class="vdd-empty">
            <VIcon icon="ri-inbox-line" size="36" class="mb-2 opacity-30" />
            <p class="text-caption">Belum ada aktivitas</p>
          </div>
          <div v-else class="vdd-timeline">
            <div
              v-for="(ev, i) in activeEvents"
              :key="(ev.id ?? i) + ev._module"
              class="vdd-tl-item"
            >
              <!-- Dot + line -->
              <div class="vdd-tl-side">
                <div
                  class="vdd-tl-dot"
                  :style="`background:${modMeta(ev._module).border}`"
                >
                  <!-- Nomor urut: 1 = terlama (paling bawah), terbesar = terbaru (paling atas) -->
                  <span class="vdd-tl-num">{{ activeEvents.length - i }}</span>
                </div>
                <div v-if="i < activeEvents.length - 1" class="vdd-tl-line" />
              </div>

              <!-- Content card -->
              <div class="vdd-tl-card" :style="`border-left: 3px solid ${modMeta(ev._module).border}`">
                <!-- Header: modul badge + tanggal -->
                <div class="vdd-tl-head">
                  <VChip :color="modMeta(ev._module).color" size="x-small" variant="tonal" class="font-weight-bold">
                    <VIcon :icon="modMeta(ev._module).icon" size="10" class="me-1" />
                    {{ modMeta(ev._module).label }}
                  </VChip>

                  <!-- Status chips per modul -->
                  <template v-if="ev._module === 'edukasi-awal'">
                    <VChip v-if="ev.status" :color="statusColor(ev.status)" size="x-small" variant="tonal">{{ ev.status }}</VChip>
                  </template>
                  <template v-else-if="ev._module === 'edukasi-lanjutan'">
                    <VChip v-if="ev.status" :color="statusColor(ev.status)" size="x-small" variant="tonal">{{ ev.status }}</VChip>
                    <VChip v-if="ev.keterangan" :color="ketColor(ev.keterangan)" size="x-small" variant="tonal">{{ ev.keterangan }}</VChip>
                  </template>
                  <template v-else-if="ev._module === 'sudah-dapat-bed'">
                    <VChip :color="ketColor(ev.keterangan)" size="x-small" variant="tonal">{{ ev.keterangan || 'Dapat Bed' }}</VChip>
                    <VChip v-if="ev.status_ranap" color="purple" size="x-small" variant="tonal">{{ ev.status_ranap }}</VChip>
                  </template>
                  <template v-else-if="ev._module === 'batal-ranap'">
                    <VChip v-if="ev.status_ok" :color="ev.status_ok === 'Bedah' ? 'success' : 'info'" size="x-small" variant="tonal">{{ ev.status_ok }}</VChip>
                    <VChip v-if="ev.status_closing" :color="closingColor(ev.status_closing)" size="x-small" variant="tonal">{{ ev.status_closing }}</VChip>
                    <VChip v-else color="secondary" size="x-small" variant="outlined">Belum Closing</VChip>
                  </template>
                  <template v-else-if="ev._module === 'up-selling'">
                    <VChip v-if="ev.alasan" :color="ev.alasan === 'Naik Kelas' ? 'success' : 'info'" size="x-small" variant="tonal">{{ ev.alasan }}</VChip>
                  </template>

                  <span class="vdd-tl-date">{{ ev.tanggal || ev.tgl_daftar || '—' }}</span>
                </div>

                <!-- Fields -->
                <div class="vdd-tl-fields">
                  <div class="vdd-tl-field">
                    <span class="vdd-lbl">Petugas</span>
                    <span class="vdd-val">{{ ev.petugas || '—' }}</span>
                  </div>
                  <div v-if="ev._module === 'edukasi-awal'" class="vdd-tl-field">
                    <span class="vdd-lbl">Durasi Tunggu</span>
                    <span class="vdd-val">{{ ev.durasi_tunggu || '—' }}</span>
                  </div>
                  <div v-if="ev._module === 'edukasi-awal' && ev.ketersediaan_kamar" class="vdd-tl-field vdd-tl-field--full">
                    <span class="vdd-lbl">Ketersediaan Kamar</span>
                    <span class="vdd-val">{{ ev.ketersediaan_kamar }}</span>
                  </div>
                  <div v-if="(ev._module === 'edukasi-lanjutan' || ev._module === 'sudah-dapat-bed') && ev.edukasi_kamar" class="vdd-tl-field vdd-tl-field--full">
                    <span class="vdd-lbl">Kamar / Ruangan</span>
                    <span class="vdd-val" style="white-space:pre-wrap">{{ ev.edukasi_kamar }}</span>
                  </div>
                  <div v-if="ev._module === 'batal-ranap' && ev.keterangan_batal" class="vdd-tl-field vdd-tl-field--full">
                    <span class="vdd-lbl">Keterangan Batal</span>
                    <span class="vdd-val">{{ ev.keterangan_batal }}</span>
                  </div>
                  <div v-if="ev._module === 'up-selling' && ev.note" class="vdd-tl-field vdd-tl-field--full">
                    <span class="vdd-lbl">Notes</span>
                    <span class="vdd-val" style="white-space:pre-wrap">{{ ev.note }}</span>
                  </div>
                  <div v-if="ev.diagnosa" class="vdd-tl-field vdd-tl-field--full">
                    <span class="vdd-lbl">Diagnosa</span>
                    <span class="vdd-val">{{ ev.diagnosa }}</span>
                  </div>
                  <div v-if="ev.note && ev._module !== 'up-selling'" class="vdd-tl-field vdd-tl-field--full">
                    <span class="vdd-lbl">Note</span>
                    <span class="vdd-val" style="white-space:pre-wrap">{{ ev.note }}</span>
                  </div>
                  <div v-if="ev.ttd_keluarga_pasien" class="vdd-tl-field vdd-tl-field--full">
                    <span class="vdd-lbl">TTD Keluarga</span>
                    <img :src="ev.ttd_keluarga_pasien" alt="TTD"
                      style="height:36px;border:1px solid #eee;border-radius:6px;margin-top:4px" />
                  </div>
                </div>
              </div>
            </div>
          </div>
        </template>
        <template v-else-if="isEdukasiMode">
          <!-- Inner tabs: hanya 2 tab -->
          <div class="vdd-tab-bar">
            <button
              class="vdd-tab-btn"
              :class="{ 'vdd-tab-btn--active': innerTab === 'riwayat' }"
              @click="innerTab = 'riwayat'"
            >
              <VIcon icon="ri-history-line" size="14" class="me-1" />
              Riwayat Edukasi
              <span v-if="allEdukasiTimeline.length" class="vdd-tab-badge">{{ allEdukasiTimeline.length }}</span>
            </button>
            <button
              class="vdd-tab-btn"
              :class="{ 'vdd-tab-btn--active': innerTab === 'info-bed' }"
              @click="innerTab = 'info-bed'"
            >
              <VIcon icon="ri-home-heart-line" size="14" class="me-1" />
              Info Bed
            </button>
          </div>

          <!-- ── Tab: Riwayat Edukasi (timeline gabungan) ── -->
          <div v-if="innerTab === 'riwayat'">
            <div v-if="!allEdukasiTimeline.length" class="vdd-empty">
              <VIcon icon="ri-inbox-line" size="36" class="mb-2 opacity-30" />
              <p class="text-caption">Belum ada riwayat edukasi</p>
            </div>
            <div v-else class="vdd-timeline">
              <div
                v-for="(rec, i) in allEdukasiTimeline"
                :key="rec.id ?? i"
                class="vdd-tl-item"
              >
                <!-- Dot + line -->
                <div class="vdd-tl-side">
                  <div class="vdd-tl-dot" :class="rec._type === 'awal' ? 'vdd-tl-dot--awal' : 'vdd-tl-dot--lanjutan'">
                    {{ allEdukasiTimeline.length - i }}
                  </div>
                  <div v-if="i < allEdukasiTimeline.length - 1" class="vdd-tl-line" />
                </div>

                <!-- Content card -->
                <div class="vdd-tl-card">
                  <!-- Header -->
                  <div class="vdd-tl-head">
                    <VChip
                      :color="rec._type === 'awal' ? 'primary' : 'warning'"
                      size="x-small" variant="tonal"
                    >
                      {{ rec._type === 'awal' ? 'Edukasi Awal' : 'Edukasi Lanjutan' }}
                    </VChip>
                    <VChip v-if="rec.keterangan" :color="ketColor(rec.keterangan)" size="x-small" variant="tonal">
                      {{ rec.keterangan }}
                    </VChip>
                    <VChip v-else-if="rec.status" :color="statusColor(rec.status)" size="x-small" variant="tonal">
                      {{ rec.status }}
                    </VChip>
                    <span class="vdd-tl-date">{{ rec.tanggal || '—' }}</span>
                  </div>

                  <!-- Fields -->
                  <div class="vdd-tl-fields">
                    <div class="vdd-tl-field">
                      <span class="vdd-lbl">Petugas</span>
                      <span class="vdd-val">{{ rec.petugas || '—' }}</span>
                    </div>
                    <div v-if="rec._type === 'awal'" class="vdd-tl-field">
                      <span class="vdd-lbl">Durasi Tunggu</span>
                      <span class="vdd-val">{{ rec.durasi_tunggu || '—' }}</span>
                    </div>
                    <div v-if="rec._type === 'lanjutan' && rec.bulan" class="vdd-tl-field">
                      <span class="vdd-lbl">Bulan</span>
                      <span class="vdd-val">{{ rec.bulan }}</span>
                    </div>
                    <div v-if="rec.edukasi_kamar" class="vdd-tl-field vdd-tl-field--full">
                      <span class="vdd-lbl">Kamar / Ruangan</span>
                      <span class="vdd-val" style="white-space:pre-wrap">{{ rec.edukasi_kamar }}</span>
                    </div>
                    <div v-if="rec.ketersediaan_kamar" class="vdd-tl-field vdd-tl-field--full">
                      <span class="vdd-lbl">Ketersediaan Kamar</span>
                      <span class="vdd-val">{{ rec.ketersediaan_kamar }}</span>
                    </div>
                    <div v-if="rec.keluarga_pasien" class="vdd-tl-field vdd-tl-field--full">
                      <span class="vdd-lbl">Keluarga Pasien</span>
                      <span class="vdd-val">{{ rec.keluarga_pasien }}</span>
                    </div>
                    <div v-if="rec.diagnosa" class="vdd-tl-field vdd-tl-field--full">
                      <span class="vdd-lbl">Diagnosa</span>
                      <span class="vdd-val">{{ rec.diagnosa }}</span>
                    </div>
                    <div v-if="rec.note" class="vdd-tl-field vdd-tl-field--full">
                      <span class="vdd-lbl">Note</span>
                      <span class="vdd-val" style="white-space:pre-wrap">{{ rec.note }}</span>
                    </div>
                    <div v-if="rec.ttd_keluarga_pasien" class="vdd-tl-field vdd-tl-field--full">
                      <span class="vdd-lbl">TTD Keluarga</span>
                      <img :src="rec.ttd_keluarga_pasien" alt="TTD"
                        style="height:36px;border:1px solid #eee;border-radius:6px;margin-top:4px" />
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- ── Tab: Info Bed ── -->
          <div v-else-if="innerTab === 'info-bed'">
            <div v-if="!item.edukasi_records?.length && !item.keterangan" class="vdd-empty">
              <VIcon icon="ri-inbox-line" size="36" class="mb-2 opacity-30" />
              <p class="text-caption">Belum ada informasi bed</p>
            </div>
            <div v-else>
              <!-- Status highlight -->
              <div class="vdd-highlight mb-3"
                :class="item.keterangan === 'Sudah Masuk Kamar' ? 'vdd-highlight--success'
                  : item.keterangan === 'Belum Diantar' ? 'vdd-highlight--warning'
                  : 'vdd-highlight--info'">
                <VIcon
                  :icon="item.keterangan === 'Sudah Masuk Kamar' ? 'ri-home-heart-line' : 'ri-walk-line'"
                  size="20"
                />
                <div>
                  <p class="vdd-lbl mb-0">Status Bed Pasien</p>
                  <p class="vdd-val fw mb-0">{{ item.keterangan || 'Menunggu Bed' }}</p>
                </div>
              </div>

              <div class="vdd-grid">
                <div class="vdd-cell vdd-cell--full">
                  <span class="vdd-lbl">No. MR</span>
                  <span class="vdd-val mono">{{ item.no_mr || '—' }}</span>
                </div>
                <div class="vdd-cell vdd-cell--full">
                  <span class="vdd-lbl">No. Reg</span>
                  <span class="vdd-val mono">{{ item.no_reg || item.edukasi_records?.[0]?.no_reg || '—' }}</span>
                </div>
                <template v-if="item.edukasi_records?.length">
                  <div v-if="item.edukasi_records.at(-1)?.edukasi_kamar" class="vdd-cell vdd-cell--full">
                    <span class="vdd-lbl">Kamar / Ruangan Terakhir</span>
                    <span class="vdd-val" style="white-space:pre-wrap">{{ item.edukasi_records.at(-1).edukasi_kamar }}</span>
                  </div>
                  <div v-if="item.edukasi_records.at(-1)?.status_ranap" class="vdd-cell vdd-cell--full">
                    <span class="vdd-lbl">Status Rawat Inap</span>
                    <VChip color="purple" variant="tonal" size="small" class="mt-1">
                      <VIcon icon="ri-hospital-fill" size="12" class="me-1" />
                      {{ item.edukasi_records.at(-1).status_ranap }}
                    </VChip>
                  </div>
                  <div class="vdd-cell">
                    <span class="vdd-lbl">Update Terakhir</span>
                    <span class="vdd-val">{{ fmtDate(item.edukasi_records.at(-1)?.updated_at) }}</span>
                  </div>
                  <div class="vdd-cell">
                    <span class="vdd-lbl">Total Sesi Edukasi</span>
                    <span class="vdd-val">{{ item.edukasi_records.length }}× pertemuan</span>
                  </div>
                </template>
              </div>
            </div>
          </div>
        </template>

        <!-- ══════════════ SUMMARY ══════════════════════════════════════════ -->
        <template v-else-if="type === 'summary'">
          <!-- Count chips -->
          <div class="vdd-count-row mb-4">
            <div v-if="item.qc"     class="vdd-count vdd-count--primary">
              <VIcon icon="ri-shield-check-line" size="16" /><span>{{ item.qc }}</span><small>Edukasi Awal</small>
            </div>
            <div v-if="item.edukasi" class="vdd-count vdd-count--warning">
              <VIcon icon="ri-book-open-line" size="16" /><span>{{ item.edukasi }}</span><small>Edu. Lanjutan</small>
            </div>
            <div v-if="item.batal"  class="vdd-count vdd-count--error">
              <VIcon icon="ri-close-circle-line" size="16" /><span>{{ item.batal }}</span><small>Batal Ranap</small>
            </div>
            <div v-if="item.up"     class="vdd-count vdd-count--success">
              <VIcon icon="ri-arrow-up-circle-line" size="16" /><span>{{ item.up }}</span><small>Up Selling</small>
            </div>
            <div v-if="item.alasan" class="vdd-count vdd-count--info">
              <VIcon icon="ri-question-answer-line" size="16" /><span>{{ item.alasan }}</span><small>Alasan</small>
            </div>
          </div>
          <div class="vdd-grid">
            <div class="vdd-cell"><span class="vdd-lbl">No. MR</span><span class="vdd-val mono">{{ item.no_mr || '—' }}</span></div>
            <div class="vdd-cell"><span class="vdd-lbl">Jaminan</span><span class="vdd-val">{{ item.jaminan || '—' }}</span></div>
            <div class="vdd-cell vdd-cell--full"><span class="vdd-lbl">Nama Pasien</span><span class="vdd-val">{{ item.nama_pasien || '—' }}</span></div>
            <div class="vdd-cell vdd-cell--full"><span class="vdd-lbl">Tanggal Terakhir</span><span class="vdd-val">{{ item.tanggal || '—' }}</span></div>
          </div>
        </template>

        <!-- ══════════════ ALASAN ════════════════════════════════════════════ -->
        <template v-else-if="type === 'alasan'">
          <div class="vdd-highlight mb-3" :class="`vdd-highlight--${alasanColor(item.alasan)}`">
            <VIcon icon="ri-question-answer-line" size="18" />
            <div>
              <p class="vdd-lbl mb-0">Alasan Kunjungan</p>
              <p class="vdd-val fw mb-0">{{ item.alasan || '—' }}</p>
            </div>
          </div>
          <div class="vdd-grid">
            <div class="vdd-cell"><span class="vdd-lbl">No. Reg</span><span class="vdd-val mono">{{ item.no_reg || '—' }}</span></div>
            <div class="vdd-cell"><span class="vdd-lbl">No. MR</span><span class="vdd-val mono">{{ item.no_mr || '—' }}</span></div>
            <div class="vdd-cell"><span class="vdd-lbl">Jaminan</span><span class="vdd-val">{{ item.jaminan || '—' }}</span></div>
            <div class="vdd-cell"><span class="vdd-lbl">Petugas</span><span class="vdd-val">{{ item.petugas || '—' }}</span></div>
            <div class="vdd-cell"><span class="vdd-lbl">Tanggal</span><span class="vdd-val">{{ item.tanggal || '—' }}</span></div>
            <div class="vdd-cell"><span class="vdd-lbl">Ruangan</span><span class="vdd-val">{{ item.nama_bangsal || item.nama_ruang || '—' }}</span></div>
            <div v-if="item.rekomendasi_karyawan_nama" class="vdd-cell vdd-cell--full">
              <span class="vdd-lbl">Karyawan RS yang Merekomendasikan</span>
              <div class="vdd-rek-card">
                <div class="vdd-rek-av">{{ item.rekomendasi_karyawan_nama?.charAt(0) ?? '?' }}</div>
                <div class="vdd-rek-info">
                  <span class="vdd-val fw">{{ item.rekomendasi_karyawan_nama }}</span>
                  <span v-if="item.rekomendasi_karyawan_nip" class="vdd-rek-nip">
                    NIP: {{ item.rekomendasi_karyawan_nip }}
                  </span>
                </div>
              </div>
            </div>
            <div v-if="item.catatan" class="vdd-cell vdd-cell--full">
              <span class="vdd-lbl">Catatan</span>
              <span class="vdd-val" style="white-space:pre-wrap">{{ item.catatan }}</span>
            </div>
          </div>
        </template>

        <!-- ══════════════ QUALITY CONTROL / EDUKASI AWAL ══════════════════ -->
        <template v-else-if="type === 'quality-control' || type === 'edukasi-awal'">
          <div class="vdd-status-row mb-3">
            <VChip v-if="item.status" :color="statusColor(item.status)" variant="tonal" size="small">
              <VIcon icon="ri-shield-check-line" size="12" class="me-1" />{{ item.status }}
            </VChip>
            <VChip v-if="item.durasi_tunggu" color="secondary" variant="tonal" size="small">
              <VIcon icon="ri-time-line" size="12" class="me-1" />{{ item.durasi_tunggu }}
            </VChip>
          </div>
          <div class="vdd-grid">
            <div class="vdd-cell"><span class="vdd-lbl">No. MR</span><span class="vdd-val mono">{{ item.no_mr || '—' }}</span></div>
            <div class="vdd-cell"><span class="vdd-lbl">No. Reg</span><span class="vdd-val mono">{{ item.no_reg || '—' }}</span></div>
            <div class="vdd-cell"><span class="vdd-lbl">Jaminan</span><span class="vdd-val">{{ item.jaminan || '—' }}</span></div>
            <div class="vdd-cell"><span class="vdd-lbl">Petugas</span><span class="vdd-val">{{ item.petugas || '—' }}</span></div>
            <div class="vdd-cell"><span class="vdd-lbl">Tanggal Input</span><span class="vdd-val">{{ item.tanggal || '—' }}</span></div>
            <div class="vdd-cell"><span class="vdd-lbl">Durasi Tunggu</span><span class="vdd-val">{{ item.durasi_tunggu || '—' }}</span></div>
            <div v-if="item.diagnosa" class="vdd-cell vdd-cell--full">
              <span class="vdd-lbl">Diagnosa</span><span class="vdd-val">{{ item.diagnosa }}</span>
            </div>
            <div v-if="item.ketersediaan_kamar" class="vdd-cell vdd-cell--full">
              <span class="vdd-lbl">Ketersediaan Kamar</span><span class="vdd-val">{{ item.ketersediaan_kamar }}</span>
            </div>
            <div v-if="item.note" class="vdd-cell vdd-cell--full">
              <span class="vdd-lbl">Note</span><span class="vdd-val" style="white-space:pre-wrap">{{ item.note }}</span>
            </div>
          </div>
        </template>

        <!-- ══════════════ EDUKASI LANJUTAN ═════════════════════════════════ -->
        <template v-else-if="type === 'edukasi-lanjutan'">
          <div class="vdd-status-row mb-3">
            <VChip v-if="item.keterangan === 'Sudah Masuk Kamar'" color="success" variant="tonal" size="small">
              <VIcon icon="ri-home-heart-line" size="12" class="me-1" />Sudah Masuk Kamar
            </VChip>
            <VChip v-else-if="item.keterangan === 'Belum Diantar'" color="orange" variant="tonal" size="small">
              <VIcon icon="ri-walk-line" size="12" class="me-1" />Belum Diantar
            </VChip>
            <VChip v-else-if="item.status" :color="statusColor(item.status)" variant="tonal" size="small">
              {{ item.status }}
            </VChip>
            <VChip v-if="item.status_ranap" color="purple" variant="tonal" size="small">
              <VIcon icon="ri-hospital-fill" size="12" class="me-1" />{{ item.status_ranap }}
            </VChip>
          </div>
          <div class="vdd-grid">
            <div class="vdd-cell"><span class="vdd-lbl">No. MR</span><span class="vdd-val mono">{{ item.no_mr || '—' }}</span></div>
            <div class="vdd-cell"><span class="vdd-lbl">No. Reg</span><span class="vdd-val mono">{{ item.no_reg || '—' }}</span></div>
            <div class="vdd-cell"><span class="vdd-lbl">Jaminan</span><span class="vdd-val">{{ item.jaminan || '—' }}</span></div>
            <div class="vdd-cell"><span class="vdd-lbl">Petugas</span><span class="vdd-val">{{ item.petugas || '—' }}</span></div>
            <div class="vdd-cell"><span class="vdd-lbl">Tanggal</span><span class="vdd-val">{{ item.tanggal || '—' }}</span></div>
            <div class="vdd-cell"><span class="vdd-lbl">Bulan</span><span class="vdd-val">{{ item.bulan || '—' }}</span></div>
            <div v-if="item.edukasi_kamar" class="vdd-cell vdd-cell--full">
              <span class="vdd-lbl">Edukasi Kamar / Ruangan</span>
              <span class="vdd-val" style="white-space:pre-wrap">{{ item.edukasi_kamar }}</span>
            </div>
            <div v-if="item.note" class="vdd-cell vdd-cell--full">
              <span class="vdd-lbl">Note Kamar</span><span class="vdd-val">{{ item.note }}</span>
            </div>
            <div v-if="item.keluarga_pasien" class="vdd-cell vdd-cell--full">
              <span class="vdd-lbl">Keluarga Pasien</span><span class="vdd-val">{{ item.keluarga_pasien }}</span>
            </div>
            <div v-if="item.ttd_keluarga_pasien" class="vdd-cell vdd-cell--full">
              <span class="vdd-lbl">Tanda Tangan Keluarga</span>
              <img :src="item.ttd_keluarga_pasien" alt="TTD"
                style="height:40px;border:1px solid #eee;border-radius:6px;margin-top:4px" />
            </div>
            <div class="vdd-cell vdd-cell--full">
              <span class="vdd-lbl">Waktu Input</span><span class="vdd-val">{{ fmtDate(item.created_at) }}</span>
            </div>
          </div>
        </template>

        <!-- ══════════════ BATAL RANAP ═══════════════════════════════════════ -->
        <template v-else-if="type === 'batal-ranap'">
          <!-- Keterangan batal highlight -->
          <div class="vdd-highlight vdd-highlight--error mb-3">
            <VIcon icon="ri-close-circle-line" size="18" />
            <div>
              <p class="vdd-lbl mb-0">Keterangan Batal</p>
              <p class="vdd-val fw mb-0">{{ item.keterangan_batal || '—' }}</p>
            </div>
          </div>
          <div class="vdd-status-row mb-3">
            <VChip v-if="item.status_ok" :color="item.status_ok === 'Bedah' ? 'success' : 'info'" variant="tonal" size="small">
              {{ item.status_ok }}
            </VChip>
            <VChip v-else color="secondary" variant="tonal" size="small">Belum Diverifikasi</VChip>
            <VChip v-if="item.status_closing" :color="closingColor(item.status_closing)" variant="tonal" size="small">
              {{ item.status_closing }}
            </VChip>
            <VChip v-else color="secondary" variant="outlined" size="small">Belum Closing</VChip>
          </div>
          <div class="vdd-grid">
            <div class="vdd-cell"><span class="vdd-lbl">No. Reg</span><span class="vdd-val mono">{{ item.no_reg || '—' }}</span></div>
            <div class="vdd-cell"><span class="vdd-lbl">No. MR</span><span class="vdd-val mono">{{ item.no_mr || '—' }}</span></div>
            <div class="vdd-cell"><span class="vdd-lbl">Tgl. Daftar</span><span class="vdd-val">{{ item.tgl_daftar || '—' }}</span></div>
            <div class="vdd-cell"><span class="vdd-lbl">Jam Daftar</span><span class="vdd-val">{{ item.jam_daftar || '—' }}</span></div>
            <div class="vdd-cell"><span class="vdd-lbl">Tgl. Input</span><span class="vdd-val">{{ item.tanggal || '—' }}</span></div>
            <div class="vdd-cell"><span class="vdd-lbl">Jam Input</span><span class="vdd-val">{{ item.jam_input || '—' }}</span></div>
            <div class="vdd-cell"><span class="vdd-lbl">Jaminan</span><span class="vdd-val">{{ item.jaminan || '—' }}</span></div>
            <div class="vdd-cell"><span class="vdd-lbl">Petugas</span><span class="vdd-val">{{ item.petugas || '—' }}</span></div>
            <div v-if="item.bed_id" class="vdd-cell">
              <span class="vdd-lbl">Kode Bed IGD</span><span class="vdd-val">{{ item.bed_id }}</span>
            </div>
            <div v-if="item.ketersediaan_kamar" class="vdd-cell vdd-cell--full">
              <span class="vdd-lbl">Ketersediaan Kamar</span><span class="vdd-val">{{ item.ketersediaan_kamar }}</span>
            </div>
            <div v-if="item.diagnosa" class="vdd-cell vdd-cell--full">
              <span class="vdd-lbl">Diagnosa</span><span class="vdd-val">{{ item.diagnosa }}</span>
            </div>
            <div v-if="item.note" class="vdd-cell vdd-cell--full">
              <span class="vdd-lbl">Note</span><span class="vdd-val" style="white-space:pre-wrap">{{ item.note }}</span>
            </div>
          </div>
        </template>

        <!-- ══════════════ UP SELLING ════════════════════════════════════════ -->
        <template v-else-if="type === 'up-selling'">
          <div class="vdd-highlight vdd-highlight--warning mb-3">
            <VIcon icon="ri-arrow-up-circle-line" size="18" />
            <div>
              <p class="vdd-lbl mb-0">Keterangan Up Selling</p>
              <p class="vdd-val fw mb-0">{{ item.alasan || '—' }}</p>
            </div>
          </div>
          <div class="vdd-grid">
            <div class="vdd-cell"><span class="vdd-lbl">No. Reg</span><span class="vdd-val mono">{{ item.no_reg || '—' }}</span></div>
            <div class="vdd-cell"><span class="vdd-lbl">No. MR</span><span class="vdd-val mono">{{ item.no_mr || '—' }}</span></div>
            <div class="vdd-cell"><span class="vdd-lbl">Tgl. Daftar</span><span class="vdd-val">{{ item.tgl_daftar || '—' }}</span></div>
            <div class="vdd-cell"><span class="vdd-lbl">Jaminan</span><span class="vdd-val">{{ item.jaminan || '—' }}</span></div>
            <div class="vdd-cell"><span class="vdd-lbl">Petugas</span><span class="vdd-val">{{ item.petugas || '—' }}</span></div>
            <div class="vdd-cell"><span class="vdd-lbl">Nama Ruang</span><span class="vdd-val">{{ item.nama_ruang || '—' }}</span></div>
            <div v-if="item.note" class="vdd-cell vdd-cell--full">
              <span class="vdd-lbl">Notes</span>
              <span class="vdd-val" style="white-space:pre-wrap">{{ item.note }}</span>
            </div>
          </div>
        </template>

      </div>

      <!-- ── Footer ─────────────────────────────────────────────────────── -->
      <div class="vdd-footer">
        <VBtn variant="outlined" rounded="lg" size="small" @click="close">Tutup</VBtn>
      </div>

    </VCard>
  </VDialog>
</template>

<style scoped>
.vdd { display: flex; flex-direction: column; max-height: 90dvh; }

.vdd-body {
  flex: 1 1 auto;
  overflow-y: auto;
  overscroll-behavior: contain;
  padding: 16px;
}

.vdd-footer {
  flex-shrink: 0;
  display: flex; gap: 8px;
  padding: 12px 16px;
  border-top: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  background: rgba(var(--v-theme-surface-variant), 0.25);
}

/* ── Banner ── */
.vdd-banner {
  position: relative; overflow: hidden; flex-shrink: 0;
  padding: 14px 16px 12px;
}
.vdd-blob { position: absolute; border-radius: 50%; background: #fff; }
.vdd-blob--1 { width: 160px; height: 160px; opacity: 0.09; top: -50px; right: -20px; }
.vdd-blob--2 { width: 70px;  height: 70px;  opacity: 0.07; bottom: -22px; right: 80px; }

.vdd-bi { position: relative; z-index: 2; display: flex; align-items: center; gap: 10px; }

.vdd-av {
  flex-shrink: 0; width: 44px; height: 44px; border-radius: 50%;
  background: rgba(255,255,255,0.25); border: 2px solid rgba(255,255,255,0.4);
  display: flex; align-items: center; justify-content: center;
  font-size: 17px; font-weight: 800; color: #fff;
}

.vdd-info { flex: 1; min-width: 0; overflow: hidden; }
.vdd-badge {
  display: inline-flex; align-items: center;
  font-size: 0.6rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.07em;
  color: rgba(255,255,255,0.75); margin: 0 0 2px;
}
.vdd-name {
  font-size: 0.92rem; font-weight: 800; color: #fff; margin: 0 0 2px;
  white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
}
.vdd-sub { font-size: 0.65rem; color: rgba(255,255,255,0.78); margin: 0; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }

.vdd-close {
  flex-shrink: 0; width: 26px; height: 26px; border-radius: 50%;
  background: rgba(255,255,255,0.2); border: none; cursor: pointer;
  display: flex; align-items: center; justify-content: center;
  color: #fff; transition: background 0.15s; align-self: flex-start;
}
.vdd-close:hover { background: rgba(255,255,255,0.35); }

/* ── Sesi pills di banner ── */
.vdd-sesi-pills {
  position: relative; z-index: 2;
  display: flex; gap: 6px; margin-top: 10px; flex-wrap: wrap;
}

.vdd-sesi-pill {
  display: inline-flex; align-items: center; gap: 4px;
  padding: 3px 8px; border-radius: 99px;
  background: rgba(255,255,255,0.18); border: 1px solid rgba(255,255,255,0.3);
  color: #fff; font-size: 0.65rem; font-weight: 600;
}
.vdd-sesi-pill span { font-size: 0.82rem; font-weight: 800; }
.vdd-sesi-pill small { font-size: 0.58rem; opacity: 0.82; font-weight: 500; }

/* ── Custom tab bar — scrollable, no horizontal overflow ── */
.vdd-tab-bar-wrap {
  overflow-x: auto;
  overflow-y: visible;
  -webkit-overflow-scrolling: touch;
  scrollbar-width: none;
  border-bottom: 2px solid rgba(var(--v-border-color), var(--v-border-opacity));
  margin: 0 0 16px;
}
.vdd-tab-bar-wrap::-webkit-scrollbar { display: none; }

.vdd-tab-bar {
  display: inline-flex;
  min-width: 100%;
  padding: 0 0 0 0;
  gap: 0;
}

.vdd-tab-btn {
  display: inline-flex; align-items: center; gap: 5px;
  padding: 9px 12px;
  font-size: 0.78rem; font-weight: 600;
  color: rgba(var(--v-theme-on-surface), 0.5);
  background: none; border: none; cursor: pointer;
  border-bottom: 2px solid transparent;
  margin-bottom: -2px;
  transition: color 0.15s, border-color 0.15s;
  white-space: nowrap;
  flex-shrink: 0;
}

.vdd-tab-btn:hover {
  color: rgba(var(--v-theme-on-surface), 0.8);
}

.vdd-tab-btn--active {
  color: rgb(var(--v-theme-primary));
  border-bottom-color: rgb(var(--v-theme-primary));
}

.vdd-tab-badge {
  display: inline-flex; align-items: center; justify-content: center;
  min-width: 18px; height: 18px; padding: 0 5px;
  border-radius: 99px; font-size: 0.62rem; font-weight: 700;
  background: rgba(var(--v-theme-primary), 0.12);
  color: rgb(var(--v-theme-primary));
}

.vdd-tab-btn--active .vdd-tab-badge {
  background: rgba(var(--v-theme-primary), 0.15);
}

/* ── Timeline ── */
.vdd-timeline {
  display: flex; flex-direction: column; gap: 0;
}

.vdd-tl-item {
  display: flex; gap: 12px;
}

.vdd-tl-side {
  display: flex; flex-direction: column; align-items: center;
  flex-shrink: 0; width: 24px;
}

.vdd-tl-dot {
  width: 24px; height: 24px; border-radius: 50%;
  display: flex; align-items: center; justify-content: center;
  flex-shrink: 0; color: #fff;
  font-size: 0.72rem; font-weight: 800; line-height: 1;
}
.vdd-tl-dot--awal     { background: rgb(var(--v-theme-primary)); }
.vdd-tl-dot--lanjutan { background: rgb(var(--v-theme-warning)); }

.vdd-tl-num {
  font-size: 0.68rem;
  font-weight: 800;
  color: #fff;
  line-height: 1;
  user-select: none;
}

.vdd-tl-line {
  width: 2px; flex: 1;
  background: rgba(var(--v-border-color), var(--v-border-opacity));
  margin: 4px 0;
  min-height: 12px;
}

.vdd-tl-card {
  flex: 1; min-width: 0;
  border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  border-radius: 10px; overflow: hidden;
  margin-bottom: 12px;
}

.vdd-tl-head {
  display: flex; align-items: center; gap: 6px; flex-wrap: wrap;
  padding: 8px 12px;
  background: rgba(var(--v-theme-surface), 1);
  border-bottom: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
}

.vdd-tl-date {
  margin-left: auto;
  font-size: 0.65rem;
  color: rgba(var(--v-theme-on-surface), 0.42);
  white-space: nowrap;
}

.vdd-tl-fields {
  display: grid; grid-template-columns: 1fr 1fr;
  padding: 0;
}

.vdd-tl-field {
  display: flex; flex-direction: column;
  padding: 8px 12px;
  border-right: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  border-bottom: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
}
.vdd-tl-field:nth-child(even) { border-right: none; }
.vdd-tl-field:last-child,
.vdd-tl-field:nth-last-child(2):nth-child(odd):not(.vdd-tl-field--full) { border-bottom: none; }
.vdd-tl-field--full { grid-column: span 2; border-right: none; }
.vdd-tl-field--full:last-child { border-bottom: none; }

/* ── Info grid ── */
.vdd-grid {
  display: grid; grid-template-columns: 1fr 1fr;
  border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  border-radius: 12px; overflow: hidden;
}
.vdd-cell {
  display: flex; flex-direction: column;
  padding: 9px 12px;
  border-right: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  border-bottom: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
}
.vdd-cell:nth-child(even) { border-right: none; }
.vdd-cell:last-child,
.vdd-cell:nth-last-child(2):nth-child(odd):not(.vdd-cell--full) { border-bottom: none; }
.vdd-cell--full { grid-column: span 2; border-right: none; }
.vdd-cell--full:last-child { border-bottom: none; }

.vdd-lbl {
  font-size: 0.6rem; text-transform: uppercase; letter-spacing: 0.07em;
  color: rgba(var(--v-theme-on-surface), 0.42); margin-bottom: 2px; font-weight: 600;
}
.vdd-val { font-size: 0.84rem; font-weight: 500; color: rgba(var(--v-theme-on-surface), 0.87); }
.vdd-val.fw { font-weight: 700; }
.vdd-val.mono { font-family: monospace; font-size: 0.82rem; }

/* ── Empty state ── */
.vdd-empty {
  display: flex; flex-direction: column; align-items: center;
  padding: 28px 0; color: rgba(var(--v-theme-on-surface), 0.4);
  text-align: center;
}

/* ── Highlight box ── */
.vdd-highlight {
  display: flex; align-items: flex-start; gap: 12px;
  padding: 12px 14px; border-radius: 12px;
}
.vdd-highlight--primary   { background: rgba(var(--v-theme-primary), 0.07); border: 1px solid rgba(var(--v-theme-primary), 0.2); color: rgb(var(--v-theme-primary)); }
.vdd-highlight--warning   { background: rgba(var(--v-theme-warning), 0.07); border: 1px solid rgba(var(--v-theme-warning), 0.2); color: rgb(var(--v-theme-warning)); }
.vdd-highlight--info      { background: rgba(var(--v-theme-info), 0.07);    border: 1px solid rgba(var(--v-theme-info),    0.2); color: rgb(var(--v-theme-info)); }
.vdd-highlight--success   { background: rgba(var(--v-theme-success), 0.07); border: 1px solid rgba(var(--v-theme-success), 0.2); color: rgb(var(--v-theme-success)); }
.vdd-highlight--error     { background: rgba(var(--v-theme-error), 0.07);   border: 1px solid rgba(var(--v-theme-error),   0.2); color: rgb(var(--v-theme-error)); }
.vdd-highlight--secondary { background: rgba(var(--v-theme-on-surface), 0.05); border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity)); color: rgba(var(--v-theme-on-surface), 0.6); }

/* ── Status row ── */
.vdd-status-row { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }

/* ── Summary count cards ── */
.vdd-count-row { display: flex; gap: 8px; flex-wrap: wrap; }
.vdd-count {
  flex: 1 1 0; min-width: 70px;
  display: flex; flex-direction: column; align-items: center;
  gap: 3px; padding: 10px 8px;
  border-radius: 12px; border: 1px solid transparent;
  font-size: 0.7rem;
}
.vdd-count span { font-size: 1.3rem; font-weight: 800; line-height: 1; }
.vdd-count small { font-size: 0.62rem; opacity: 0.7; text-align: center; }

.vdd-count--primary  { background: rgba(var(--v-theme-primary), 0.08); border-color: rgba(var(--v-theme-primary), 0.2); color: rgb(var(--v-theme-primary)); }
.vdd-count--warning  { background: rgba(var(--v-theme-warning), 0.08); border-color: rgba(var(--v-theme-warning), 0.2); color: rgb(var(--v-theme-warning)); }
.vdd-count--error    { background: rgba(var(--v-theme-error),   0.08); border-color: rgba(var(--v-theme-error),   0.2); color: rgb(var(--v-theme-error)); }
.vdd-count--success  { background: rgba(var(--v-theme-success), 0.08); border-color: rgba(var(--v-theme-success), 0.2); color: rgb(var(--v-theme-success)); }
.vdd-count--info     { background: rgba(var(--v-theme-info),    0.08); border-color: rgba(var(--v-theme-info),    0.2); color: rgb(var(--v-theme-info)); }

/* ── Rekomendasi karyawan card ── */
.vdd-rek-card {
  display: flex; align-items: center; gap: 10px;
  margin-top: 6px; padding: 8px 10px;
  background: rgba(var(--v-theme-primary), 0.05);
  border: 1px solid rgba(var(--v-theme-primary), 0.15);
  border-radius: 9px;
}
.vdd-rek-av {
  flex-shrink: 0; width: 32px; height: 32px; border-radius: 50%;
  background: rgb(var(--v-theme-primary)); color: #fff;
  display: flex; align-items: center; justify-content: center;
  font-size: 0.8rem; font-weight: 800;
}
.vdd-rek-info {
  display: flex; flex-direction: column; min-width: 0;
}
.vdd-rek-nip {
  font-size: 0.65rem; color: rgba(var(--v-theme-on-surface), 0.45);
  margin-top: 1px;
}

/* ── Patient meta strip (history-pasien) ── */
.vdd-patient-meta {
  display: flex; gap: 0;
  border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  border-radius: 10px; overflow: hidden;
}
.vdd-pm-item {
  flex: 1; display: flex; flex-direction: column;
  padding: 8px 12px;
  border-right: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
}
.vdd-pm-item:last-child { border-right: none; }

/* ── Tab badge variants (history-pasien) ── */
.vdd-tab-badge--primary { background: rgba(var(--v-theme-primary), 0.12); color: rgb(var(--v-theme-primary)); }
.vdd-tab-badge--warning { background: rgba(var(--v-theme-warning), 0.12); color: rgb(var(--v-theme-warning)); }
.vdd-tab-badge--error   { background: rgba(var(--v-theme-error),   0.12); color: rgb(var(--v-theme-error)); }
.vdd-tab-badge--orange  { background: rgba(230,100,20, 0.12); color: #e66414; }
</style>
