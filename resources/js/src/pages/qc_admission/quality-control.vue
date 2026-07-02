<script setup>
import { useQualityControlStore } from '@/stores/useQualityControlStore'
import QCFormDialog from '@/views/qc-admission/quality-control/QCFormDialog.vue'
import SummaryCards from '@/components/SummaryCards.vue'
import PageHero     from '@/components/PageHero.vue'
import axios from 'axios'

const store = useQualityControlStore()

// ── State ─────────────────────────────────────────────────────────────────────
const showForm          = ref(false)
const editItem          = ref(null)
const detailItem        = ref(null)
const showDetail        = ref(false)
const showDeleteConfirm = ref(false)
const deleteTarget      = ref(null)
const loading           = ref(false)
const processing        = ref(false)
const snackbar          = ref({ show: false, msg: '', color: 'success' })

// ── Date filter ───────────────────────────────────────────────────────────────
function todayStr() {
  const d = new Date(), p = n => String(n).padStart(2, '0')
  return `${d.getFullYear()}-${p(d.getMonth()+1)}-${p(d.getDate())}`
}
const todayFormatted = computed(() =>
  new Date().toLocaleDateString('id-ID', { weekday:'long', day:'numeric', month:'long', year:'numeric' })
)
const dateFrom     = ref(todayStr())
const dateTo       = ref(todayStr())
const search       = ref('')
const filterStatus = ref('all')

// ── Live clock (untuk countdown) ─────────────────────────────────────────────
const now = ref(Date.now())
let ticker         = null
let autoProTimer   = null

// ── Auto-process: jalankan tiap 2 menit (silent) + sekali saat halaman buka ──
// Menggantikan cron job yang mungkin tidak aktif di environment dev.
async function processAutomatic(silent = true) {
  if (processing.value) return
  processing.value = true
  try {
    const { data } = await axios.post('/api/quality-control/process-edukasi-lanjutan')
    if (!silent) toast(data.output || 'Proses selesai.', 'info')
    if ((data.count ?? 0) > 0) {
      // Ada yang dipindahkan → reload supaya badge countdown hilang
      await load()
    }
  } catch (e) {
    if (!silent) toast('Gagal proses.', 'error')
  } finally {
    processing.value = false
  }
}

onMounted(async () => {
  ticker       = setInterval(() => { now.value = Date.now() }, 10_000)
  autoProTimer = setInterval(() => processAutomatic(true), 120_000)
  await load()
  // Cek setelah data dimuat — apakah ada yang perlu dipindahkan
  processAutomatic(true)
})
onUnmounted(() => {
  clearInterval(ticker)
  clearInterval(autoProTimer)
})

// ── Data ──────────────────────────────────────────────────────────────────────
const records = computed(() => store.records ?? [])

const stats = computed(() => {
  const lanjutan = records.value.filter(r =>
    r.created_at && (now.value - new Date(r.created_at).getTime()) / 1000 >= 7200
  ).length
  return { total: records.value.length, edukasi: records.value.length - lanjutan, lanjutan }
})

const filtered = computed(() => {
  let d = records.value
  if (search.value.trim()) {
    const q = search.value.toLowerCase()
    d = d.filter(r =>
      r.no_reg?.toLowerCase().includes(q) ||
      r.no_mr?.toLowerCase().includes(q) ||
      r.nama_pasien?.toLowerCase().includes(q)
    )
  }
  if (filterStatus.value === 'edukasi')  d = d.filter(r => !isLanjutan(r))
  if (filterStatus.value === 'lanjutan') d = d.filter(r => isLanjutan(r))
  return d
})

// ── Helpers ───────────────────────────────────────────────────────────────────
function isLanjutan(item) {
  return item.created_at && (now.value - new Date(item.created_at).getTime()) / 1000 >= 7200
}
function getCountdown(item) {
  if (!item.created_at) return null
  const rem = Math.max(0, 7200 - Math.floor((now.value - new Date(item.created_at).getTime()) / 1000))
  if (rem === 0) return null
  const h = String(Math.floor(rem / 3600)).padStart(2, '0')
  const m = String(Math.floor((rem % 3600) / 60)).padStart(2, '0')
  return `${h}:${m}`
}

// ── Handlers ──────────────────────────────────────────────────────────────────
function openAdd()         { editItem.value = null; showForm.value = true }
function openRow(item)     { detailItem.value = item; showDetail.value = true }
function toast(msg, color = 'success') { snackbar.value = { show: true, msg, color } }
function resetFilter()     { search.value = ''; filterStatus.value = 'all'; dateFrom.value = todayStr(); dateTo.value = todayStr() }

async function onSaved()   { showForm.value = false; toast('Data QC disimpan.'); await load() }

async function doDelete() {
  if (!deleteTarget.value) return
  loading.value = true
  await store.destroy(deleteTarget.value.id)
  loading.value = false
  showDeleteConfirm.value = false
  showDetail.value        = false
  deleteTarget.value      = null
  toast('Data dihapus.')
  await load()
}

async function load() {
  loading.value = true
  try {
    await store.fetchRecords({
      search:    search.value || undefined,
      date_from: dateFrom.value || undefined,
      date_to:   dateTo.value   || undefined,
      per_page:  500,
    })
  } catch(e) { console.error(e) }
  finally { loading.value = false }
}

watch([dateFrom, dateTo], () => load())
</script>

<template>
  <div class="qc-page">

    <!-- ── Header ─────────────────────────────────────────────────────────── -->
    <PageHero
      icon="ri-shield-check-line"
      badge="Quality Control"
      title="Quality Control Admisi"
      subtitle="Pasien otomatis pindah ke Edukasi Lanjutan setelah 2 jam"
      color-from="#0EA5E9"
      color-to="#0369A1"
      :pills="[
        { icon: 'ri-calendar-line', text: todayFormatted },
        { icon: 'ri-database-line', text: `${stats.total} data hari ini` },
      ]"
    >
      <template #actions>
        <VBtn color="white" variant="elevated" rounded="pill" size="small"
          style="color:#0369A1;font-weight:700" @click="openAdd">
          <VIcon icon="ri-add-line" size="16" class="me-1" />Input Quality Control
        </VBtn>
      </template>
    </PageHero>

    <!-- ── Stats ──────────────────────────────────────────────────────────── -->
    <SummaryCards :cards="[
      { value: stats.total,    label: 'Total QC Hari Ini',     color: 'primary', icon: 'ri-shield-check-line' },
      { value: stats.edukasi,  label: 'Sedang Edukasi',        color: 'success', icon: 'ri-book-line' },
      { value: stats.lanjutan, label: 'Siap Edukasi Lanjutan', color: 'warning', icon: 'ri-timer-flash-line' },
    ]" />

    <!-- ── Filter ─────────────────────────────────────────────────────────── -->
    <VCard elevation="0" border rounded="xl" class="mb-4">
      <VCardText class="pa-3">
        <VRow dense align="center">
          <VCol cols="12" sm="4">
            <VTextField v-model="search" label="Cari pasien / No. Reg / No. MR"
              prepend-inner-icon="ri-search-line"
              variant="outlined" density="compact" hide-details clearable rounded="lg" />
          </VCol>
          <VCol cols="6" sm="2">
            <VTextField v-model="dateFrom" label="Dari" type="date"
              variant="outlined" density="compact" hide-details rounded="lg" />
          </VCol>
          <VCol cols="6" sm="2">
            <VTextField v-model="dateTo" label="Sampai" type="date"
              variant="outlined" density="compact" hide-details rounded="lg" />
          </VCol>
          <VCol cols="12" sm="auto">
            <VBtn size="small" variant="text" color="secondary" block @click="resetFilter">Reset</VBtn>
          </VCol>
        </VRow>
        <!-- Filter Status chips -->
        <div class="d-flex gap-2 mt-3 flex-wrap">
          <VChip v-for="opt in [
            { v:'all',      label:'Semua',                color:'primary', icon: null },
            { v:'edukasi',  label:'Edukasi',              color:'success', icon:'ri-book-line' },
            { v:'lanjutan', label:'Siap Edukasi Lanjutan',color:'warning', icon:'ri-timer-flash-line' },
          ]" :key="opt.v"
            :color="filterStatus === opt.v ? opt.color : 'default'"
            :variant="filterStatus === opt.v ? 'elevated' : 'outlined'"
            :prepend-icon="opt.icon ?? undefined"
            size="small" class="cursor-pointer"
            @click="filterStatus = filterStatus === opt.v && opt.v !== 'all' ? 'all' : opt.v"
          >{{ opt.label }}</VChip>
        </div>
      </VCardText>
    </VCard>

    <!-- ── Info bar ───────────────────────────────────────────────────────── -->
    <div class="d-flex align-center gap-3 mb-4 flex-wrap">
      <VChip size="small" color="primary" variant="tonal" rounded="pill">{{ filtered.length }} data</VChip>
      <span class="text-caption" style="color:var(--qc-text-2)">dari {{ records.length }} total</span>
      <VSpacer />
      <!-- Indikator auto-process -->
      <div v-if="processing" class="d-flex align-center gap-1">
        <VProgressCircular indeterminate size="12" width="2" color="primary" />
        <span class="text-caption" style="color:var(--qc-text-2)">Memproses...</span>
      </div>
      <span v-else class="text-caption" style="color:var(--qc-text-2)">Klik baris untuk aksi</span>
    </div>

    <!-- ── List ───────────────────────────────────────────────────────────── -->
    <VCard elevation="0" border rounded="xl" class="overflow-hidden">
      <div v-if="loading" class="text-center py-12">
        <VProgressCircular indeterminate color="primary" size="32" />
        <p class="text-caption mt-3" style="color:var(--qc-text-2)">Memuat data...</p>
      </div>

      <div v-else-if="!filtered.length" class="text-center py-16" style="color:var(--qc-text-2)">
        <VIcon icon="ri-inbox-line" size="52" class="mb-3 opacity-30" />
        <p class="text-body-1 font-weight-semibold mb-1">Belum ada data</p>
        <p class="text-caption mb-4">Klik "Input QC" untuk menambah data hari ini</p>
        <VBtn color="primary" variant="tonal" rounded="lg" size="small" @click="openAdd">
          <VIcon icon="ri-add-line" size="15" class="me-1" />Input QC
        </VBtn>
      </div>

      <div v-else>
        <div v-for="(item, idx) in filtered" :key="item.id"
          class="qc-row" :class="{ 'qc-row--bordered': idx < filtered.length - 1 }"
          @click="openRow(item)"
        >
          <VAvatar color="primary" variant="tonal" size="40" rounded="lg" class="flex-shrink-0">
            <span class="font-weight-bold" style="font-size:14px">{{ item.nama_pasien?.charAt(0) ?? '?' }}</span>
          </VAvatar>

          <div class="flex-grow-1 min-width-0">
            <div class="d-flex align-center gap-2 flex-wrap">
              <span class="font-weight-semibold" style="font-size:0.9rem;color:var(--qc-text)">{{ item.nama_pasien }}</span>
              <VChip :color="isLanjutan(item) ? 'warning' : 'success'" size="x-small" variant="tonal">
                {{ isLanjutan(item) ? '⏰ Siap Lanjutan' : '📚 Edukasi' }}
              </VChip>
            </div>
            <div class="d-flex align-center gap-3 mt-1 flex-wrap">
              <span class="text-caption" style="color:var(--qc-text-2)">
                <VIcon icon="ri-hashtag" size="11" />{{ item.no_reg }}
              </span>
              <span class="text-caption" style="color:var(--qc-text-2)">
                <VIcon icon="ri-user-line" size="11" class="me-1" />{{ item.petugas }}
              </span>
              <span v-if="item.edukasi_kamar" class="text-caption" style="color:var(--qc-text-2)">
                <VIcon icon="ri-hospital-line" size="11" class="me-1" />{{ item.edukasi_kamar }}
              </span>
            </div>
          </div>

          <div class="text-end flex-shrink-0">
            <p class="text-caption mb-0 font-mono" style="color:var(--qc-text-2)">{{ item.jam_input }}</p>
            <div v-if="!isLanjutan(item) && getCountdown(item)" class="d-flex align-center gap-1 justify-end mt-1">
              <VIcon icon="ri-timer-line" size="11" color="warning" />
              <span class="text-caption font-mono" style="color:rgb(var(--v-theme-warning))">{{ getCountdown(item) }}</span>
            </div>
            <div v-else-if="isLanjutan(item)" class="d-flex align-center gap-1 justify-end mt-1">
              <VIcon icon="ri-arrow-right-circle-line" size="11" color="warning" />
              <span class="text-caption" style="color:rgb(var(--v-theme-warning))">Siap dipindah</span>
            </div>
          </div>
        </div>
      </div>
    </VCard>

    <!-- ── Form Dialog ────────────────────────────────────────────────────── -->
    <QCFormDialog v-model="showForm" :edit-item="editItem" @saved="onSaved" />

    <!-- ── Detail Dialog ──────────────────────────────────────────────────── -->
    <VDialog v-model="showDetail" max-width="460" scrollable>
      <VCard v-if="detailItem" rounded="xl" class="overflow-hidden">

        <!-- Banner header sesuai primary color -->
        <div class="qc-detail-header">
          <div class="blob b1" /><div class="blob b2" /><div class="blob b3" />
          <div class="d-flex align-center gap-3" style="position:relative;z-index:2">
            <div class="qc-detail-av">{{ detailItem.nama_pasien?.charAt(0) ?? '?' }}</div>
            <div class="flex-grow-1 min-width-0">
              <p class="qc-detail-name text-truncate">{{ detailItem.nama_pasien }}</p>
              <p class="qc-detail-sub mb-0">
                {{ detailItem.no_reg }} · MR {{ detailItem.no_mr }}
              </p>
            </div>
            <button class="qc-close-btn" @click="showDetail = false">
              <VIcon icon="ri-close-line" size="16" />
            </button>
          </div>
          <!-- Status pills -->
          <div class="d-flex gap-2 mt-3 flex-wrap" style="position:relative;z-index:2">
            <span class="qc-pill" :class="isLanjutan(detailItem) ? 'qc-pill--warn' : 'qc-pill--ok'">
              <VIcon :icon="isLanjutan(detailItem) ? 'ri-arrow-right-circle-line' : 'ri-book-line'" size="12" class="me-1" />
              {{ isLanjutan(detailItem) ? 'Siap Edukasi Lanjutan' : 'Sedang Edukasi' }}
            </span>
            <span v-if="!isLanjutan(detailItem) && getCountdown(detailItem)" class="qc-pill qc-pill--timer">
              <VIcon icon="ri-timer-flash-line" size="12" class="me-1" />
              {{ getCountdown(detailItem) }} lagi
            </span>
          </div>
        </div>

        <!-- Info grid -->
        <div class="qc-info-grid">
          <div class="qc-info-cell"><span class="qc-lbl">Tanggal</span><span class="qc-val">{{ detailItem.tanggal }}</span></div>
          <div class="qc-info-cell"><span class="qc-lbl">Jam Input</span><span class="qc-val">{{ detailItem.jam_input }}</span></div>
          <div class="qc-info-cell"><span class="qc-lbl">Jaminan</span><span class="qc-val">{{ detailItem.jaminan || '—' }}</span></div>
          <div class="qc-info-cell"><span class="qc-lbl">Petugas</span><span class="qc-val">{{ detailItem.petugas || '—' }}</span></div>
          <div class="qc-info-cell qc-info-cell--full"><span class="qc-lbl">Edukasi Kamar</span><span class="qc-val">{{ detailItem.edukasi_kamar || '—' }}</span></div>
          <div class="qc-info-cell qc-info-cell--full"><span class="qc-lbl">Note / Kamar</span><span class="qc-val">{{ detailItem.note || '—' }}</span></div>
          <div class="qc-info-cell qc-info-cell--full"><span class="qc-lbl">Keluarga Pasien</span><span class="qc-val">{{ detailItem.keluarga_pasien || '—' }}</span></div>
        </div>

        <!-- TTD -->
        <div v-if="detailItem.ttd_keluarga_pasien" class="px-5 pb-2 pt-1">
          <p class="qc-lbl mb-2">Tanda Tangan Keluarga</p>
          <div style="border:1.5px solid var(--qc-border);border-radius:10px;overflow:hidden;background:#fafafa">
            <img :src="detailItem.ttd_keluarga_pasien" alt="TTD"
              style="max-height:80px;width:100%;object-fit:contain" />
          </div>
        </div>

        <!-- ── Action buttons — jelas, full-width, responsif ─────────────── -->
        <div class="qc-action-bar">
          <VBtn
            variant="outlined" rounded="lg" class="qc-action-btn"
            @click="showDetail = false"
          >Tutup</VBtn>
          <VBtn
            color="primary" variant="tonal" rounded="lg" class="qc-action-btn qc-action-btn--grow"
            @click="editItem = {...detailItem}; showDetail = false; showForm = true"
          >
            <VIcon icon="ri-pencil-line" size="15" class="me-1" />Edit Data
          </VBtn>
          <VBtn
            color="error" variant="tonal" rounded="lg" class="qc-action-btn"
            @click="deleteTarget = detailItem; showDeleteConfirm = true"
          >
            <VIcon icon="ri-delete-bin-line" size="15" />
          </VBtn>
        </div>
      </VCard>
    </VDialog>

    <!-- ── Delete Confirm ─────────────────────────────────────────────────── -->
    <VDialog v-model="showDeleteConfirm" max-width="340">
      <VCard rounded="xl">
        <VCardText class="pa-6 text-center">
          <VAvatar color="error" variant="tonal" size="56" rounded="xl" class="mb-3">
            <VIcon icon="ri-delete-bin-2-line" size="26" />
          </VAvatar>
          <p class="text-h6 font-weight-bold mb-1">Hapus Data?</p>
          <p class="text-body-2" style="color:var(--qc-text-2)">
            Data <strong>{{ deleteTarget?.nama_pasien }}</strong> akan dihapus permanen.
          </p>
        </VCardText>
        <div class="d-flex gap-2 px-5 pb-5">
          <VBtn variant="outlined" rounded="lg" class="flex-grow-1" @click="showDeleteConfirm = false">Batal</VBtn>
          <VBtn color="error" rounded="lg" class="flex-grow-1" :loading="loading" @click="doDelete">
            <VIcon icon="ri-delete-bin-line" size="15" class="me-1" />Hapus
          </VBtn>
        </div>
      </VCard>
    </VDialog>

    <VSnackbar v-model="snackbar.show" :color="snackbar.color" timeout="3000" location="bottom right" rounded="xl">
      {{ snackbar.msg }}
      <template #actions><VBtn variant="text" size="small" @click="snackbar.show=false">✕</VBtn></template>
    </VSnackbar>

  </div>
</template>

<style scoped>
/* ── Row list ──────────────────────────────────────────────────────────────── */
.qc-row {
  display: flex; align-items: center; gap: 12px;
  padding: 14px 16px; cursor: pointer; transition: background 0.12s;
}
.qc-row:hover { background: var(--qc-green-light); }
.qc-row--bordered { border-bottom: 1px solid var(--qc-border); }

/* ── Detail banner ─────────────────────────────────────────────────────────── */
.qc-detail-header {
  position: relative; overflow: hidden;
  background: linear-gradient(135deg, #0369A1 0%, #0EA5E9 60%, #38BDF8 100%);
  padding: 20px 20px 16px;
  border-radius: 20px 20px 0 0;
}
.blob {
  position: absolute; border-radius: 50%; background: rgba(255,255,255,0.08);
}
.b1 { width: 180px; height: 180px; top: -60px; right: -30px; }
.b2 { width: 90px;  height: 90px;  bottom: -35px; right: 100px; }
.b3 { width: 55px;  height: 55px;  top: 5px; right: 180px; background: rgba(255,255,255,0.05); }

.qc-detail-av {
  width: 52px; height: 52px; border-radius: 14px; flex-shrink: 0;
  background: rgba(255,255,255,0.2); border: 2px solid rgba(255,255,255,0.35);
  display: flex; align-items: center; justify-content: center;
  font-size: 20px; font-weight: 800; color: #fff;
}
.qc-detail-name { font-size: 1.05rem; font-weight: 700; color: #fff; margin: 0 0 2px; }
.qc-detail-sub  { font-size: 0.75rem; color: rgba(255,255,255,0.75); }

.qc-close-btn {
  background: rgba(255,255,255,0.18); border: none; cursor: pointer;
  width: 30px; height: 30px; border-radius: 50%; flex-shrink: 0;
  display: flex; align-items: center; justify-content: center;
  color: #fff; transition: background 0.15s;
}
.qc-close-btn:hover { background: rgba(255,255,255,0.32); }

.qc-pill {
  display: inline-flex; align-items: center;
  padding: 3px 10px; border-radius: 20px;
  font-size: 0.72rem; font-weight: 700;
}
.qc-pill--ok    { background: rgba(255,255,255,0.2); color: #fff; }
.qc-pill--warn  { background: rgba(255,180,0,0.28); color: #ffe066; }
.qc-pill--timer { background: rgba(255,255,255,0.15); color: rgba(255,255,255,0.9); }

/* ── Info grid ─────────────────────────────────────────────────────────────── */
.qc-info-grid {
  display: grid; grid-template-columns: 1fr 1fr;
  padding: 4px 0;
}
.qc-info-cell {
  display: flex; flex-direction: column;
  padding: 11px 20px;
  border-bottom: 1px solid var(--qc-border);
  border-right: 1px solid var(--qc-border);
}
.qc-info-cell:nth-child(even) { border-right: none; }
.qc-info-cell--full { grid-column: span 2; border-right: none; }
.qc-lbl {
  font-size: 0.63rem; text-transform: uppercase; letter-spacing: 0.07em;
  color: var(--qc-text-2); margin-bottom: 2px; font-weight: 600;
}
.qc-val { font-size: 0.875rem; font-weight: 500; color: var(--qc-text); }

/* ── Action bar — full-width, responsif ─────────────────────────────────────── */
.qc-action-bar {
  display: flex; gap: 8px;
  padding: 14px 16px;
  border-top: 1px solid var(--qc-border);
  flex-wrap: wrap;
}
.qc-action-btn        { min-height: 40px; font-size: 0.85rem; font-weight: 600; }
.qc-action-btn--grow  { flex: 1; }

@media (max-width: 400px) {
  .qc-action-bar { flex-direction: column; }
  .qc-action-btn { width: 100%; }
}
</style>
