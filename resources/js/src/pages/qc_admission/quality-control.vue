<script setup>
import { useQualityControlStore } from '@/stores/useQualityControlStore'
import QCFormDialog from '@/views/qc-admission/quality-control/QCFormDialog.vue'
import SummaryCards from '@/components/SummaryCards.vue'
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

// ── Date filter — default hari ini ────────────────────────────────────────────
function todayStr() {
  const d = new Date(), p = n => String(n).padStart(2,'0')
  return `${d.getFullYear()}-${p(d.getMonth()+1)}-${p(d.getDate())}`
}
const dateFrom = ref(todayStr())
const dateTo   = ref(todayStr())
const search   = ref('')
const filterStatus = ref('all') // 'all' | 'edukasi' | 'lanjutan'

// ── Live countdown timer ──────────────────────────────────────────────────────
const now = ref(Date.now())
let ticker = null
onMounted(() => { ticker = setInterval(() => { now.value = Date.now() }, 10000) })
onUnmounted(() => clearInterval(ticker))

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

// ── Countdown util ────────────────────────────────────────────────────────────
function getCountdown(item) {
  if (!item.created_at) return null
  const rem = Math.max(0, 7200 - Math.floor((now.value - new Date(item.created_at).getTime()) / 1000))
  if (rem === 0) return null
  const h = String(Math.floor(rem / 3600)).padStart(2,'0')
  const m = String(Math.floor((rem % 3600) / 60)).padStart(2,'0')
  return `${h}:${m}`
}
function isLanjutan(item) {
  return item.created_at && (now.value - new Date(item.created_at).getTime()) / 1000 >= 7200
}

// ── Handlers ──────────────────────────────────────────────────────────────────
function openAdd()  { editItem.value = null; showForm.value = true }
function openRow(item) { detailItem.value = item; showDetail.value = true }

async function onSaved() {
  showForm.value = false
  toast('Data QC disimpan.')
  await load()
}

async function doDelete() {
  if (!deleteTarget.value) return
  loading.value = true
  await store.destroy(deleteTarget.value.id)
  loading.value = false
  showDeleteConfirm.value = false
  showDetail.value = false
  deleteTarget.value = null
  toast('Data dihapus.')
  await load()
}

async function processManual() {
  processing.value = true
  try {
    const { data } = await axios.post('/api/quality-control/process-edukasi-lanjutan')
    toast(data.output || 'Proses selesai.', 'info')
    await load()
  } catch { toast('Gagal proses.', 'error') }
  finally { processing.value = false }
}

function toast(msg, color = 'success') { snackbar.value = { show: true, msg, color } }

function resetFilter() { search.value = ''; filterStatus.value = 'all'; dateFrom.value = todayStr(); dateTo.value = todayStr() }

async function load() {
  loading.value = true
  try {
    await store.fetchRecords({
      search: search.value || undefined,
      date_from: dateFrom.value || undefined,
      date_to: dateTo.value || undefined,
      per_page: 500,
    })
  } catch(e) { console.error(e) }
  finally { loading.value = false }
}

// Watch date filter — re-fetch dari API saat tanggal berubah
watch([dateFrom, dateTo], () => load())

onMounted(load)
</script>

<template>
  <div class="qc-page">

    <!-- ── Header ─────────────────────────────────────────────────────────── -->
    <div class="page-hero page-hero--qc">
      <div class="page-hero__content">
        <div class="page-hero__badge">
          <VIcon icon="ri-shield-check-line" size="12" />Quality Control
        </div>
        <h1 class="page-hero__title">Quality Control Admisi</h1>
        <p class="page-hero__subtitle">Pasien otomatis pindah ke Edukasi Lanjutan setelah 2 jam</p>
      </div>
      <div class="page-hero__actions">
        <VBtn icon variant="text" color="white" size="small" :loading="processing" @click="processManual">
          <VIcon icon="ri-play-circle-line" size="18" />
        </VBtn>
        <VBtn icon variant="text" color="white" size="small" :loading="loading" @click="load">
          <VIcon icon="ri-refresh-line" size="18" />
        </VBtn>
        <VBtn color="white" variant="elevated" rounded="pill" size="small" style="color:#0F4C35;font-weight:700" @click="openAdd">
          <VIcon icon="ri-add-line" size="16" class="me-1" />Input QC
        </VBtn>
      </div>
      <VIcon icon="ri-shield-check-line" class="page-hero__icon" />
    </div>

    <!-- ── Stats ──────────────────────────────────────────────────────────── -->
    <SummaryCards :cards="[
      { value: stats.total,    label: 'Total QC',           color: 'primary', icon: 'ri-shield-check-line' },
      { value: stats.edukasi,  label: 'Sedang Edukasi',     color: 'success', icon: 'ri-book-line' },
      { value: stats.lanjutan, label: 'Siap Edukasi Lanjutan', color: 'warning', icon: 'ri-timer-flash-line' },
    ]" />

    <!-- ── Filter ─────────────────────────────────────────────────────────── -->
    <VCard elevation="0" border rounded="xl" class="mb-4">
      <VCardText class="pa-3">
        <VRow dense align="center">
          <VCol cols="12" sm="4">
            <VTextField v-model="search" label="Cari pasien / No. Reg / No. MR" prepend-inner-icon="ri-search-line"
              variant="outlined" density="compact" hide-details clearable rounded="lg" />
          </VCol>
          <VCol cols="6" sm="2">
            <VTextField v-model="dateFrom" label="Dari" type="date" variant="outlined" density="compact" hide-details rounded="lg" />
          </VCol>
          <VCol cols="6" sm="2">
            <VTextField v-model="dateTo" label="Sampai" type="date" variant="outlined" density="compact" hide-details rounded="lg" />
          </VCol>
          <VCol cols="12" sm="auto">
            <VBtn size="small" variant="text" color="secondary" block @click="resetFilter; load()">Reset</VBtn>
          </VCol>
        </VRow>
        <!-- Filter Status: Edukasi / Siap Edukasi Lanjutan -->
        <div class="d-flex gap-2 mt-3 flex-wrap">
          <VChip
            :color="filterStatus === 'all' ? 'primary' : 'default'"
            :variant="filterStatus === 'all' ? 'elevated' : 'outlined'"
            size="small" class="cursor-pointer"
            @click="filterStatus = 'all'"
          >Semua</VChip>
          <VChip
            :color="filterStatus === 'edukasi' ? 'success' : 'default'"
            :variant="filterStatus === 'edukasi' ? 'elevated' : 'outlined'"
            size="small" class="cursor-pointer"
            prepend-icon="ri-book-line"
            @click="filterStatus = filterStatus === 'edukasi' ? 'all' : 'edukasi'"
          >Edukasi</VChip>
          <VChip
            :color="filterStatus === 'lanjutan' ? 'warning' : 'default'"
            :variant="filterStatus === 'lanjutan' ? 'elevated' : 'outlined'"
            size="small" class="cursor-pointer"
            prepend-icon="ri-timer-flash-line"
            @click="filterStatus = filterStatus === 'lanjutan' ? 'all' : 'lanjutan'"
          >Siap Edukasi Lanjutan</VChip>
        </div>
      </VCardText>
    </VCard>

    <!-- ── Info bar ───────────────────────────────────────────────────────── -->
    <div class="d-flex align-center gap-3 mb-4 flex-wrap">
      <VChip size="small" color="primary" variant="tonal" rounded="pill">{{ filtered.length }} data</VChip>
      <span class="text-caption" style="color:var(--qc-text-2)">dari {{ records.length }} total</span>
      <VSpacer />
      <span class="text-caption" style="color:var(--qc-text-2)">Klik pasien untuk aksi</span>
    </div>

    <!-- ── List / Cards ───────────────────────────────────────────────────── -->
    <VCard elevation="0" border rounded="xl" class="overflow-hidden">

      <!-- Loading -->
      <div v-if="loading" class="text-center py-12">
        <VProgressCircular indeterminate color="primary" size="32" />
        <p class="text-caption mt-3" style="color:var(--qc-text-2)">Memuat data...</p>
      </div>

      <!-- Empty -->
      <div v-else-if="!filtered.length" class="text-center py-16" style="color:var(--qc-text-2)">
        <VIcon icon="ri-inbox-line" size="52" class="mb-3 opacity-30" />
        <p class="text-body-1 font-weight-semibold mb-1">Belum ada data</p>
        <p class="text-caption mb-4">Klik "Input QC" untuk menambah data hari ini</p>
        <VBtn color="primary" variant="tonal" rounded="lg" size="small" @click="openAdd">
          <VIcon icon="ri-add-line" size="15" class="me-1" />Input QC
        </VBtn>
      </div>

      <!-- Data rows -->
      <div v-else>
        <div
          v-for="(item, idx) in filtered"
          :key="item.id"
          class="qc-row"
          :class="{ 'qc-row--bordered': idx < filtered.length - 1 }"
          @click="openRow(item)"
        >
          <!-- Avatar -->
          <VAvatar color="primary" variant="tonal" size="40" rounded="lg" class="flex-shrink-0">
            <span class="font-weight-bold" style="font-size:14px">{{ item.nama_pasien?.charAt(0) ?? '?' }}</span>
          </VAvatar>

          <!-- Main info -->
          <div class="flex-grow-1 min-width-0">
            <div class="d-flex align-center gap-2 flex-wrap">
              <span class="font-weight-semibold" style="font-size:0.9rem;color:var(--qc-text)">{{ item.nama_pasien }}</span>
              <VChip
                :color="isLanjutan(item) ? 'warning' : 'success'"
                size="x-small" variant="tonal"
              >{{ isLanjutan(item) ? 'Edukasi Lanjutan' : 'Edukasi' }}</VChip>
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

          <!-- Countdown / time -->
          <div class="text-end flex-shrink-0">
            <p class="text-caption mb-0 font-mono" style="color:var(--qc-text-2)">{{ item.jam_input }}</p>
            <div v-if="!isLanjutan(item) && getCountdown(item)" class="d-flex align-center gap-1 justify-end mt-1">
              <VIcon icon="ri-timer-line" size="11" color="warning" />
              <span class="text-caption font-mono" style="color:rgb(var(--v-theme-warning))">{{ getCountdown(item) }}</span>
            </div>
          </div>
        </div>
      </div>
    </VCard>

    <!-- ── Form Dialog ────────────────────────────────────────────────────── -->
    <QCFormDialog v-model="showForm" :edit-item="editItem" @saved="onSaved" />

    <!-- ── Detail / Action Sheet ──────────────────────────────────────────── -->
    <VDialog v-model="showDetail" max-width="440" scrollable>
      <VCard v-if="detailItem" rounded="xl" class="detail-card">
        <!-- Gradient header -->
        <div class="detail-header">
          <div class="d-flex align-center gap-3">
            <div class="detail-avatar">
              <span>{{ detailItem.nama_pasien?.charAt(0) ?? '?' }}</span>
            </div>
            <div class="flex-grow-1 min-width-0">
              <p class="detail-name text-truncate">{{ detailItem.nama_pasien }}</p>
              <div class="d-flex align-center gap-2 flex-wrap mt-1">
                <span class="detail-id">{{ detailItem.no_reg }}</span>
                <span class="detail-sep">·</span>
                <span class="detail-id">MR {{ detailItem.no_mr }}</span>
              </div>
            </div>
            <VBtn icon variant="text" color="white" size="small" @click="showDetail = false">
              <VIcon icon="ri-close-line" size="18" />
            </VBtn>
          </div>

          <!-- Status + countdown -->
          <div class="d-flex align-center gap-2 mt-3 flex-wrap">
            <div class="status-badge" :class="isLanjutan(detailItem) ? 'status-badge--warn' : 'status-badge--ok'">
              <VIcon :icon="isLanjutan(detailItem) ? 'ri-arrow-right-circle-line' : 'ri-book-line'" size="13" class="me-1" />
              {{ isLanjutan(detailItem) ? 'Edukasi Lanjutan' : 'Sedang Edukasi' }}
            </div>
            <div v-if="!isLanjutan(detailItem) && getCountdown(detailItem)" class="countdown-badge">
              <VIcon icon="ri-timer-flash-line" size="12" class="me-1" />
              {{ getCountdown(detailItem) }} lagi pindah
            </div>
          </div>
        </div>

        <VCardText class="pa-0">
          <!-- Info grid -->
          <div class="info-grid">
            <div class="info-cell">
              <span class="info-lbl">Tanggal</span>
              <span class="info-val">{{ detailItem.tanggal }}</span>
            </div>
            <div class="info-cell">
              <span class="info-lbl">Jam Input</span>
              <span class="info-val">{{ detailItem.jam_input }}</span>
            </div>
            <div class="info-cell">
              <span class="info-lbl">Jaminan</span>
              <span class="info-val">{{ detailItem.jaminan || '—' }}</span>
            </div>
            <div class="info-cell">
              <span class="info-lbl">Petugas</span>
              <span class="info-val">{{ detailItem.petugas || '—' }}</span>
            </div>
            <div class="info-cell info-cell--full">
              <span class="info-lbl">Edukasi Kamar</span>
              <span class="info-val">{{ detailItem.edukasi_kamar || '—' }}</span>
            </div>
            <div class="info-cell info-cell--full">
              <span class="info-lbl">Note / Kamar</span>
              <span class="info-val">{{ detailItem.note || '—' }}</span>
            </div>
            <div class="info-cell info-cell--full">
              <span class="info-lbl">Keluarga Pasien</span>
              <span class="info-val">{{ detailItem.keluarga_pasien || '—' }}</span>
            </div>
          </div>

          <!-- TTD -->
          <div v-if="detailItem.ttd_keluarga_pasien" class="ttd-section">
            <p class="info-lbl mb-2">Tanda Tangan Keluarga</p>
            <div class="ttd-wrap">
              <img :src="detailItem.ttd_keluarga_pasien" alt="TTD"
                style="max-height:90px;width:100%;object-fit:contain;background:#fff;border-radius:8px" />
            </div>
          </div>
        </VCardText>

        <!-- Actions -->
        <div class="detail-actions">
          <VBtn variant="outlined" rounded="lg" size="small" @click="showDetail = false">
            Tutup
          </VBtn>
          <VBtn
            color="primary" variant="tonal" rounded="lg" size="small"
            @click="editItem = {...detailItem}; showDetail = false; showForm = true"
          >
            <VIcon icon="ri-pencil-line" size="14" class="me-1" />Edit
          </VBtn>
          <VBtn
            color="error" variant="tonal" rounded="lg" size="small"
            @click="deleteTarget = detailItem; showDeleteConfirm = true"
          >
            <VIcon icon="ri-delete-bin-line" size="14" />
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
          <p class="text-body-2" style="color:var(--qc-text-2)">Data <strong>{{ deleteTarget?.nama_pasien }}</strong> akan dihapus.</p>
        </VCardText>
        <div class="d-flex gap-2 px-5 pb-5">
          <VBtn variant="outlined" rounded="lg" class="flex-grow-1" @click="showDeleteConfirm = false">Batal</VBtn>
          <VBtn color="error" rounded="lg" class="flex-grow-1" :loading="loading" @click="doDelete">Hapus</VBtn>
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
.qc-row {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 14px 16px;
  cursor: pointer;
  transition: background 0.12s;
}
.qc-row:hover { background: var(--qc-green-light); }
.qc-row--bordered { border-bottom: 1px solid var(--qc-border); }

/* ── Detail card ─────────────────────────────────────────────────────────── */
.detail-header {
  background: linear-gradient(135deg, #0F4C35, #00B37E);
  padding: 20px 20px 16px;
  border-radius: 20px 20px 0 0;
}
.detail-avatar {
  width: 52px; height: 52px;
  border-radius: 14px;
  background: rgba(255,255,255,0.2);
  border: 2px solid rgba(255,255,255,0.3);
  display: flex; align-items: center; justify-content: center;
  flex-shrink: 0;
  font-size: 20px; font-weight: 800; color: #fff;
}
.detail-name { font-size: 1.05rem; font-weight: 700; color: #fff; margin: 0; }
.detail-id   { font-size: 0.75rem; color: rgba(255,255,255,0.75); }
.detail-sep  { color: rgba(255,255,255,0.4); }

.status-badge {
  display: inline-flex; align-items: center;
  padding: 4px 10px; border-radius: 20px;
  font-size: 0.72rem; font-weight: 700;
}
.status-badge--ok   { background: rgba(255,255,255,0.2); color: #fff; }
.status-badge--warn { background: rgba(255,180,0,0.25); color: #ffe066; }

.countdown-badge {
  display: inline-flex; align-items: center;
  background: rgba(255,180,0,0.15);
  color: #ffe066;
  padding: 3px 9px; border-radius: 20px;
  font-size: 0.7rem; font-weight: 600;
}

/* Info grid */
.info-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 0;
  padding: 4px 0;
}
.info-cell {
  display: flex; flex-direction: column;
  padding: 12px 20px;
  border-bottom: 1px solid var(--qc-border);
}
.info-cell--full { grid-column: span 2; }
.info-lbl {
  font-size: 0.65rem; text-transform: uppercase;
  letter-spacing: 0.07em; color: var(--qc-text-2);
  margin-bottom: 3px; font-weight: 600;
}
.info-val {
  font-size: 0.875rem; font-weight: 500; color: var(--qc-text);
}

/* TTD section */
.ttd-section {
  padding: 14px 20px 4px;
}
.ttd-wrap {
  border: 1.5px solid var(--qc-border);
  border-radius: 10px;
  overflow: hidden;
  background: #fafafa;
}

/* Actions */
.detail-actions {
  display: flex; gap: 8px; padding: 14px 20px;
  border-top: 1px solid var(--qc-border);
}
</style>
