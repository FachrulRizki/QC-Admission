<script setup>
import { useQualityControlStore } from '@/stores/useQualityControlStore'
import QCFormDialog from '@/views/qc-admission/quality-control/QCFormDialog.vue'
import QCDataTable  from '@/views/qc-admission/quality-control/QCDataTable.vue'

const store = useQualityControlStore()

const showDialog        = ref(false)
const editItem          = ref(null)
const showDeleteConfirm = ref(false)
const deleteTarget      = ref(null)
const loading           = ref(false)
const lastRefresh       = ref(null)

// ── Filters ────────────────────────────────────────────────────────────────────
const STATUS_OPTIONS = [
  { title: 'Semua Status',      value: null },
  { title: 'Edukasi',           value: 'Edukasi' },
  { title: 'Edukasi Lanjutan',  value: 'Edukasi lanjutan' },
]
const filterStatus   = ref(null)
const filterSearch   = ref('')
const filterDateFrom = ref('')
const filterDateTo   = ref('')
const filterPetugas  = ref('')

// ── Data dari store/API ────────────────────────────────────────────────────────
const records = computed(() => store.records ?? [])

const stats = computed(() => ({
  total:           records.value.length,
  edukasi:         records.value.filter(r => r.status === 'Edukasi').length,
  edukasiLanjutan: records.value.filter(r => r.status === 'Edukasi lanjutan').length,
  lanjutanPct: records.value.length
    ? Math.round((records.value.filter(r => r.status === 'Edukasi lanjutan').length / records.value.length) * 100)
    : 0,
}))

const filtered = computed(() => {
  let data = records.value
  if (filterStatus.value)         data = data.filter(r => r.status === filterStatus.value)
  if (filterPetugas.value.trim()) data = data.filter(r => r.petugas?.toLowerCase().includes(filterPetugas.value.toLowerCase()))
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
  } catch (e) {
    console.error('Delete error', e)
  } finally {
    loading.value = false
    showDeleteConfirm.value = false
    deleteTarget.value = null
  }
}

async function onSaved() {
  showDialog.value = false
  await doRefresh()
}

function resetFilters() {
  filterStatus.value   = null
  filterSearch.value   = ''
  filterDateFrom.value = ''
  filterDateTo.value   = ''
  filterPetugas.value  = ''
}

async function doRefresh() {
  loading.value = true
  try {
    await store.fetchRecords({
      search:    filterSearch.value   || undefined,
      status:    filterStatus.value   || undefined,
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
        <p class="page-hero__subtitle">Monitoring dan pencatatan data QC rawat inap · Durasi &ge; 2 jam → auto Edukasi Lanjutan</p>
      </div>
      <div class="d-flex gap-2 align-center" style="position:relative;z-index:2">
        <VBtn icon variant="text" color="white" size="small" :loading="loading" title="Refresh" @click="doRefresh">
          <VIcon icon="ri-refresh-line" />
        </VBtn>
        <VBtn color="white" variant="elevated" rounded="lg" prepend-icon="ri-add-line" style="color:#667eea" @click="openAdd">
          Input QC
        </VBtn>
      </div>
      <VIcon icon="ri-shield-check-line" class="page-hero__icon" />
    </div>

    <!-- Info auto-trigger -->
    <VAlert type="info" variant="tonal" border="start" density="compact" class="mb-4" closable>
      <div class="text-caption">
        <strong>Auto Edukasi Lanjutan:</strong>
        Jika status <strong>"Edukasi Lanjutan"</strong> dan durasi tunggu mencapai <strong>≥ 2 jam</strong>,
        data otomatis masuk ke menu Edukasi Lanjutan. Status hanya: <strong>Edukasi</strong> atau <strong>Edukasi Lanjutan</strong>.
      </div>
    </VAlert>

    <!-- Stats — clickable filter -->
    <VRow dense class="mb-4">
      <VCol cols="6" sm="4">
        <VCard
          elevation="0" border rounded="lg"
          class="stat-card text-center pa-4 cursor-pointer"
          :class="filterStatus === null ? 'stat-active' : ''"
          @click="filterStatus = null"
        >
          <p class="text-h4 font-weight-bold text-primary mb-0">{{ stats.total }}</p>
          <p class="text-caption text-medium-emphasis mb-0">Total QC</p>
        </VCard>
      </VCol>
      <VCol cols="6" sm="4">
        <VCard
          elevation="0" border rounded="lg"
          class="stat-card text-center pa-4 cursor-pointer"
          :class="filterStatus === 'Edukasi' ? 'stat-active-success' : ''"
          @click="filterStatus = filterStatus === 'Edukasi' ? null : 'Edukasi'"
        >
          <p class="text-h4 font-weight-bold mb-0" style="color:rgb(var(--v-theme-success))">{{ stats.edukasi }}</p>
          <p class="text-caption text-medium-emphasis mb-0">
            <VIcon icon="ri-book-line" size="12" class="me-1" />Edukasi
          </p>
        </VCard>
      </VCol>
      <VCol cols="12" sm="4">
        <VCard
          elevation="0" border rounded="lg"
          class="stat-card pa-4 cursor-pointer"
          :class="filterStatus === 'Edukasi lanjutan' ? 'stat-active-warning' : ''"
          @click="filterStatus = filterStatus === 'Edukasi lanjutan' ? null : 'Edukasi lanjutan'"
        >
          <div class="d-flex align-center justify-space-between mb-2">
            <div>
              <p class="text-h4 font-weight-bold mb-0" style="color:rgb(var(--v-theme-warning))">{{ stats.edukasiLanjutan }}</p>
              <p class="text-caption text-medium-emphasis mb-0">
                <VIcon icon="ri-book-open-line" size="12" class="me-1" />Edukasi Lanjutan
              </p>
            </div>
            <VChip color="warning" variant="tonal" size="small">{{ stats.lanjutanPct }}%</VChip>
          </div>
          <VProgressLinear :model-value="stats.lanjutanPct" color="warning" rounded height="4" bg-color="warning" bg-opacity="0.15" />
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
            <VSelect
              v-model="filterStatus"
              :items="STATUS_OPTIONS"
              item-title="title"
              item-value="value"
              variant="outlined" density="compact" hide-details
              placeholder="Status"
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
        </VRow>
        <div class="d-flex justify-end gap-2 mt-2">
          <VBtn size="small" variant="text" color="secondary" prepend-icon="ri-refresh-line" @click="resetFilters">Reset</VBtn>
          <VBtn size="small" variant="tonal" color="primary" prepend-icon="ri-loop-left-line" :loading="loading" @click="doRefresh">Refresh</VBtn>
        </div>
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
        Update: {{ lastRefresh.toLocaleTimeString('id-ID', { hour:'2-digit', minute:'2-digit', second:'2-digit' }) }}
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
  </div>
</template>

<style scoped>
.stat-card { transition: box-shadow 0.2s, transform 0.15s; cursor: pointer; }
.stat-card:hover { box-shadow: 0 4px 16px rgba(var(--v-shadow-key-umbra-color), 0.1) !important; transform: translateY(-1px); }
</style>
