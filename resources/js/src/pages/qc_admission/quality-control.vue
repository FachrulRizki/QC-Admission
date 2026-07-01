<script setup>
import { useQualityControlStore } from '@/stores/useQualityControlStore'
import QCFormDialog from '@/views/qc-admission/quality-control/QCFormDialog.vue'
import QCDataTable  from '@/views/qc-admission/quality-control/QCDataTable.vue'
import axios from 'axios'

const store = useQualityControlStore()

const showDialog        = ref(false)
const editItem          = ref(null)
const showDeleteConfirm = ref(false)
const deleteTarget      = ref(null)
const loading           = ref(false)
const processing        = ref(false)
const lastRefresh       = ref(null)
const snackbar          = ref({ show: false, message: '', color: 'success' })

// ── Filters ────────────────────────────────────────────────────────────────────
const filterSearch   = ref('')
const filterDateFrom = ref('')
const filterDateTo   = ref('')
const filterPetugas  = ref('')

// ── Data dari store/API ────────────────────────────────────────────────────────
const records = computed(() => store.records ?? [])

// Hitung berapa yang sudah >= 2 jam (akan masuk Edukasi Lanjutan)
const now = ref(Date.now())
let clockTick = null
onMounted(() => { clockTick = setInterval(() => { now.value = Date.now() }, 5000) })
onUnmounted(() => clearInterval(clockTick))

const stats = computed(() => {
  const siapLanjutan = records.value.filter(r => {
    if (!r.created_at) return false
    return (now.value - new Date(r.created_at).getTime()) / 1000 >= 7200
  }).length
  return {
    total:       records.value.length,
    edukasi:     records.value.length - siapLanjutan,
    siapLanjutan,
  }
})

const filtered = computed(() => {
  let data = records.value
  if (filterPetugas.value.trim()) {
    data = data.filter(r => r.petugas?.toLowerCase().includes(filterPetugas.value.toLowerCase()))
  }
  if (filterSearch.value.trim()) {
    const q = filterSearch.value.toLowerCase()
    data = data.filter(r =>
      r.no_reg?.toLowerCase().includes(q) ||
      r.no_mr?.toLowerCase().includes(q) ||
      r.nama_pasien?.toLowerCase().includes(q)
    )
  }
  if (filterDateFrom.value) data = data.filter(r => r.tanggal >= filterDateFrom.value)
  if (filterDateTo.value)   data = data.filter(r => r.tanggal <= filterDateTo.value)
  return data
})

// ── Handlers ──────────────────────────────────────────────────────────────────
function openAdd()        { editItem.value = null;        showDialog.value = true }
function openEdit(item)   { editItem.value = { ...item }; showDialog.value = true }
function openDelete(item) { deleteTarget.value = item;    showDeleteConfirm.value = true }

async function confirmDelete() {
  if (!deleteTarget.value) return
  loading.value = true
  try {
    await store.destroy(deleteTarget.value.id)
    snackbar.value = { show: true, message: 'Data berhasil dihapus.', color: 'success' }
  } catch (e) {
    snackbar.value = { show: true, message: 'Gagal menghapus data.', color: 'error' }
  } finally {
    loading.value = false
    showDeleteConfirm.value = false
    deleteTarget.value = null
  }
}

async function onSaved() {
  showDialog.value = false
  snackbar.value = { show: true, message: 'Data QC berhasil disimpan.', color: 'success' }
  await doRefresh()
}

function resetFilters() {
  filterSearch.value   = ''
  filterDateFrom.value = ''
  filterDateTo.value   = ''
  filterPetugas.value  = ''
}

// Trigger manual proses auto-Edukasi Lanjutan
async function processManual() {
  processing.value = true
  try {
    const { data } = await axios.post('/api/quality-control/process-edukasi-lanjutan')
    snackbar.value = {
      show: true,
      message: data.output || 'Proses selesai.',
      color: 'info',
    }
    await doRefresh()
  } catch {
    snackbar.value = { show: true, message: 'Gagal menjalankan proses.', color: 'error' }
  } finally {
    processing.value = false
  }
}

async function doRefresh() {
  loading.value = true
  try {
    await store.fetchRecords({
      search:    filterSearch.value   || undefined,
      petugas:   filterPetugas.value  || undefined,
      date_from: filterDateFrom.value || undefined,
      date_to:   filterDateTo.value   || undefined,
      per_page:  200,
    })
    lastRefresh.value = new Date()
  } catch (e) {
    console.error('Fetch QC error', e)
  } finally {
    loading.value = false
  }
}

onMounted(() => doRefresh())
</script>

<template>
  <div>
    <!-- Hero -->
    <div class="page-hero page-hero--qc mb-5">
      <div class="page-hero__content">
        <div class="page-hero__badge">
          <VIcon icon="ri-shield-check-line" size="13" />
          QC Admission · Quality Control
        </div>
        <h1 class="page-hero__title">Quality Control Admisi</h1>
        <p class="page-hero__subtitle">Pasien otomatis pindah ke Edukasi Lanjutan 2 jam setelah entry</p>
      </div>
      <div class="d-flex gap-2 align-center" style="position:relative;z-index:2">
        <VTooltip text="Proses manual Edukasi Lanjutan">
          <template #activator="{ props: tp }">
            <VBtn v-bind="tp" icon variant="text" color="white" size="small" :loading="processing" @click="processManual">
              <VIcon icon="ri-play-circle-line" />
            </VBtn>
          </template>
        </VTooltip>
        <VBtn icon variant="text" color="white" size="small" :loading="loading" @click="doRefresh">
          <VIcon icon="ri-refresh-line" />
        </VBtn>
        <VBtn color="white" variant="elevated" rounded="lg" prepend-icon="ri-add-line" style="color:#667eea" @click="openAdd">
          Input QC
        </VBtn>
      </div>
      <VIcon icon="ri-shield-check-line" class="page-hero__icon" />
    </div>

    <!-- Alur info -->
    <VAlert type="info" variant="tonal" border="start" density="compact" class="mb-4" closable>
      <div class="text-caption">
        <strong>Alur:</strong>
        Entry pasien → Status <strong>Edukasi</strong> →
        <VIcon icon="ri-timer-flash-line" size="12" class="mx-1" />
        Sistem cek tiap menit → Jika sudah <strong>≥ 2 jam</strong> sejak entry → otomatis masuk <strong>Edukasi Lanjutan</strong>.
        Gunakan tombol <VIcon icon="ri-play-circle-line" size="12" class="mx-1" /> untuk trigger manual.
      </div>
    </VAlert>

    <!-- Stats -->
    <VRow dense class="mb-4">
      <VCol cols="4">
        <VCard elevation="0" border rounded="lg" class="stat-card text-center pa-4">
          <p class="text-h4 font-weight-bold text-primary mb-0">{{ stats.total }}</p>
          <p class="text-caption text-medium-emphasis mb-0">Total QC</p>
        </VCard>
      </VCol>
      <VCol cols="4">
        <VCard elevation="0" border rounded="lg" class="stat-card text-center pa-4">
          <p class="text-h4 font-weight-bold mb-0" style="color:rgb(var(--v-theme-success))">{{ stats.edukasi }}</p>
          <p class="text-caption text-medium-emphasis mb-0">
            <VIcon icon="ri-book-line" size="12" class="me-1" />Sedang Edukasi
          </p>
        </VCard>
      </VCol>
      <VCol cols="4">
        <VCard elevation="0" border rounded="lg" class="stat-card text-center pa-4"
          :class="stats.siapLanjutan > 0 ? 'stat-active-warning' : ''">
          <p class="text-h4 font-weight-bold mb-0" style="color:rgb(var(--v-theme-warning))">
            {{ stats.siapLanjutan }}
          </p>
          <p class="text-caption text-medium-emphasis mb-0">
            <VIcon icon="ri-timer-flash-line" size="12" class="me-1" />Siap → Edukasi Lanjutan
          </p>
          <p v-if="stats.siapLanjutan > 0" class="text-caption text-warning mt-1 mb-0">
            (belum diproses scheduler)
          </p>
        </VCard>
      </VCol>
    </VRow>

    <!-- Filter bar -->
    <VCard elevation="0" border rounded="lg" class="mb-4">
      <VCardText class="py-3">
        <VRow dense align="center">
          <VCol cols="12" sm="4">
            <VTextField
              v-model="filterSearch"
              placeholder="Cari No. Reg / No. MR / Nama Pasien..."
              prepend-inner-icon="ri-search-line"
              variant="outlined" density="compact" hide-details clearable
            />
          </VCol>
          <VCol cols="6" sm="2">
            <VTextField
              v-model="filterPetugas"
              placeholder="Petugas"
              prepend-inner-icon="ri-nurse-line"
              variant="outlined" density="compact" hide-details clearable
            />
          </VCol>
          <VCol cols="6" sm="2">
            <VTextField v-model="filterDateFrom" label="Dari" type="date" variant="outlined" density="compact" hide-details />
          </VCol>
          <VCol cols="6" sm="2">
            <VTextField v-model="filterDateTo" label="Sampai" type="date" variant="outlined" density="compact" hide-details />
          </VCol>
          <VCol cols="auto">
            <VBtn size="small" variant="text" color="secondary" prepend-icon="ri-refresh-line" @click="resetFilters">Reset</VBtn>
          </VCol>
        </VRow>
      </VCardText>
    </VCard>

    <!-- Result count -->
    <div class="d-flex align-center justify-space-between mb-3 flex-wrap gap-2">
      <div class="d-flex align-center gap-2">
        <VChip size="small" color="primary" variant="tonal">{{ filtered.length }} data</VChip>
        <span class="text-caption text-disabled">dari {{ records.length }} total</span>
      </div>
      <span v-if="lastRefresh" class="text-caption text-disabled">
        <VIcon icon="ri-time-line" size="13" class="me-1" />
        {{ lastRefresh.toLocaleTimeString('id-ID', { hour:'2-digit', minute:'2-digit', second:'2-digit' }) }}
      </span>
    </div>

    <!-- Table -->
    <QCDataTable :items="filtered" :loading="loading" @edit="openEdit" @delete="openDelete" />

    <!-- Form dialog -->
    <QCFormDialog v-model="showDialog" :edit-item="editItem" @saved="onSaved" />

    <!-- Delete confirm -->
    <VDialog v-model="showDeleteConfirm" max-width="380">
      <VCard rounded="lg">
        <VCardText class="pa-6 text-center">
          <VAvatar color="error" variant="tonal" size="60" rounded="xl" class="mb-4">
            <VIcon icon="ri-delete-bin-2-line" size="30" />
          </VAvatar>
          <h6 class="text-h6 font-weight-bold mb-2">Hapus Data?</h6>
          <p class="text-body-2 text-medium-emphasis mb-0">
            Data <strong>{{ deleteTarget?.nama_pasien }}</strong> akan dihapus permanen.
          </p>
        </VCardText>
        <VCardActions class="px-6 pb-5 d-flex gap-2 pt-0">
          <VBtn variant="outlined" rounded="lg" class="flex-grow-1" @click="showDeleteConfirm = false">Batal</VBtn>
          <VBtn color="error" rounded="lg" class="flex-grow-1" prepend-icon="ri-delete-bin-line" :loading="loading" @click="confirmDelete">Hapus</VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <VSnackbar v-model="snackbar.show" :color="snackbar.color" timeout="4000" location="bottom right" rounded="lg">
      {{ snackbar.message }}
      <template #actions>
        <VBtn variant="text" size="small" @click="snackbar.show = false">Tutup</VBtn>
      </template>
    </VSnackbar>
  </div>
</template>

<style scoped>
.stat-card { transition: box-shadow 0.2s, transform 0.15s; }
.stat-card:hover { box-shadow: 0 4px 16px rgba(var(--v-shadow-key-umbra-color), 0.1) !important; transform: translateY(-1px); }
.font-mono { font-family: 'JetBrains Mono', 'Fira Code', monospace !important; }
</style>
