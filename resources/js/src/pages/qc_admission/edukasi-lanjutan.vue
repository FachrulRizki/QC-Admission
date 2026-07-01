<script setup>
import { useEdukasiLanjutanStore } from '@/stores/useEdukasiLanjutanStore'
import EdukasiPatientCard  from '@/views/qc-admission/edukasi-lanjutan/EdukasiPatientCard.vue'
import EdukasiDetailDialog from '@/views/qc-admission/edukasi-lanjutan/EdukasiDetailDialog.vue'

const store    = useEdukasiLanjutanStore()
const loading  = ref(false)
const syncing  = ref(false)
const lastSync = ref(null)

// ── Filters ───────────────────────────────────────────────────────────────────
const searchQuery    = ref('')
const filterBulan    = ref('All')
const filterStatus   = ref('All')
const filterPetugas  = ref('')
const filterDateFrom = ref('')
const filterDateTo   = ref('')

// ── Real data dari store ──────────────────────────────────────────────────────
const records   = computed(() => store.records ?? [])
const bulanList = computed(() => ['All', ...new Set(records.value.map(r => r.bulan).filter(Boolean))])

// Hitung jumlah sesi per no_mr
const sesiPerMr = computed(() => {
  const map = {}
  records.value.forEach(r => {
    map[r.no_mr] = (map[r.no_mr] ?? 0) + 1
  })
  return map
})

// Dedupe: tampilkan satu card per pasien (no_mr), pakai data terbaru
const uniquePatients = computed(() => {
  const map = {}
  records.value.forEach(r => {
    if (!map[r.no_mr] || r.id > map[r.no_mr].id) {
      map[r.no_mr] = r
    }
  })
  return Object.values(map)
})

const filtered = computed(() => {
  let data = uniquePatients.value
  if (filterBulan.value !== 'All')   data = data.filter(r => r.bulan === filterBulan.value)
  if (filterStatus.value !== 'All')  data = data.filter(r => r.status === filterStatus.value)
  if (filterPetugas.value.trim())    data = data.filter(r => r.petugas?.toLowerCase().includes(filterPetugas.value.toLowerCase()))
  if (filterDateFrom.value)          data = data.filter(r => r.tanggal >= filterDateFrom.value)
  if (filterDateTo.value)            data = data.filter(r => r.tanggal <= filterDateTo.value)
  if (searchQuery.value.trim()) {
    const q = searchQuery.value.toLowerCase()
    data = data.filter(r =>
      r.nama_pasien?.toLowerCase().includes(q) ||
      r.no_mr?.toLowerCase().includes(q) ||
      r.no_reg?.toLowerCase().includes(q) ||
      r.petugas?.toLowerCase().includes(q)
    )
  }
  return data
})

const stats = computed(() => ({
  total:    uniquePatients.value.length,
  menunggu: uniquePatients.value.filter(r => r.status === 'Menunggu').length,
  selesai:  uniquePatients.value.filter(r => r.status === 'Selesai').length,
  selesaiPct: uniquePatients.value.length
    ? Math.round((uniquePatients.value.filter(r => r.status === 'Selesai').length / uniquePatients.value.length) * 100)
    : 0,
}))

async function doRefresh() {
  loading.value = true
  try {
    await store.fetchRecords({ per_page: 200,
      search:  searchQuery.value     || undefined,
      status:  filterStatus.value !== 'All' ? filterStatus.value : undefined,
      petugas: filterPetugas.value   || undefined,
    })
  } catch (e) {
    console.error('Edukasi fetch error', e)
  } finally {
    loading.value = false
  }
}

async function syncFromRsus() {
  syncing.value = true
  try {
    const result = await store.syncFromRsus()
    lastSync.value = new Date()
    if (!result?.success) {
      console.warn('Sync RSUS: ', result?.message)
    }
  } catch (e) {
    lastSync.value = new Date()
  } finally {
    syncing.value = false
  }
}

// ── Detail dialog ─────────────────────────────────────────────────────────────
const detailDialog    = ref(false)
const selectedPatient = ref(null)
const dialogMode      = ref('view')

function openDetail(patient) { selectedPatient.value = { ...patient }; dialogMode.value = 'view'; detailDialog.value = true }
function openEdit(patient)   { selectedPatient.value = { ...patient }; dialogMode.value = 'edit'; detailDialog.value = true }

async function onSaved() {
  detailDialog.value = false
  await doRefresh()
}

function resetFilters() {
  filterBulan.value   = 'All'
  filterStatus.value  = 'All'
  searchQuery.value   = ''
  filterPetugas.value = ''
  filterDateFrom.value = ''
  filterDateTo.value   = ''
}

onMounted(() => doRefresh())
</script>

<template>
  <div>
    <!-- Hero -->
    <div class="page-hero page-hero--edukasi mb-5">
      <div class="page-hero__content">
        <div class="page-hero__badge">
          <VIcon icon="ri-book-open-line" size="13" />
          QC Admission · Edukasi Lanjutan
        </div>
        <h1 class="page-hero__title">Edukasi Lanjutan</h1>
        <p class="page-hero__subtitle">
          Auto-trigger dari QC ≥ 2 jam · Status bed real-time dari SIMRS/ERM
        </p>
      </div>
      <div class="d-flex gap-2 align-center" style="position:relative;z-index:2">
        <VBtn icon variant="text" color="white" size="small" :loading="loading" @click="doRefresh">
          <VIcon icon="ri-refresh-line" />
        </VBtn>
        <VBtn
          color="white" variant="elevated" rounded="lg" size="small"
          prepend-icon="ri-refresh-line"
          style="color:#f5576c"
          :loading="syncing"
          @click="syncFromRsus"
        >
          Sync SIMRS
        </VBtn>
        <span v-if="lastSync" class="text-caption" style="color:rgba(255,255,255,0.75)">
          <VIcon icon="ri-time-line" size="12" class="me-1" />
          {{ lastSync.toLocaleTimeString('id-ID', { hour:'2-digit', minute:'2-digit' }) }}
        </span>
      </div>
      <VIcon icon="ri-book-open-line" class="page-hero__icon" />
    </div>

    <!-- Info -->
    <VAlert type="info" variant="tonal" border="start" density="compact" class="mb-4" closable>
      <div class="text-caption">
        <strong>Cara kerja:</strong>
        Data masuk otomatis dari QC saat status <strong>"Edukasi Lanjutan"</strong> + durasi tunggu <strong>≥ 2 jam</strong>.
        Klik <strong>Sync SIMRS</strong> untuk cek status bed terbaru — pasien yang sudah dapat bed otomatis <strong>Selesai</strong>.
      </div>
    </VAlert>

    <!-- Stats — clickable filter -->
    <VRow dense class="mb-4">
      <VCol cols="4">
        <VCard elevation="0" border rounded="lg" class="stat-card text-center pa-3 cursor-pointer"
          :class="filterStatus === 'All' ? 'stat-active' : ''"
          @click="filterStatus = 'All'">
          <p class="text-h4 font-weight-bold text-primary mb-0">{{ stats.total }}</p>
          <p class="text-caption text-medium-emphasis mb-0">Total</p>
        </VCard>
      </VCol>
      <VCol cols="4">
        <VCard elevation="0" border rounded="lg" class="stat-card text-center pa-3 cursor-pointer"
          :class="filterStatus === 'Menunggu' ? 'stat-active-warning' : ''"
          @click="filterStatus = filterStatus === 'Menunggu' ? 'All' : 'Menunggu'">
          <p class="text-h4 font-weight-bold mb-0" style="color:rgb(var(--v-theme-warning))">{{ stats.menunggu }}</p>
          <p class="text-caption text-medium-emphasis mb-0">
            <VIcon icon="ri-time-line" size="12" class="me-1" />Menunggu Bed
          </p>
        </VCard>
      </VCol>
      <VCol cols="4">
        <VCard elevation="0" border rounded="lg" class="stat-card pa-3 cursor-pointer"
          :class="filterStatus === 'Selesai' ? 'stat-active-success' : ''"
          @click="filterStatus = filterStatus === 'Selesai' ? 'All' : 'Selesai'">
          <div class="d-flex align-center justify-space-between mb-1">
            <div>
              <p class="text-h4 font-weight-bold mb-0" style="color:rgb(var(--v-theme-success))">{{ stats.selesai }}</p>
              <p class="text-caption text-medium-emphasis mb-0">
                <VIcon icon="ri-check-double-line" size="12" class="me-1" />Sudah Dapat Bed
              </p>
            </div>
            <VChip color="success" variant="tonal" size="small">{{ stats.selesaiPct }}%</VChip>
          </div>
          <VProgressLinear :model-value="stats.selesaiPct" color="success" rounded height="3" bg-color="success" bg-opacity="0.15" />
        </VCard>
      </VCol>
    </VRow>

    <!-- Filter bar -->
    <VCard elevation="0" border rounded="lg" class="mb-4">
      <VCardText class="py-3">
        <VRow dense align="center">
          <VCol cols="12" sm="4">
            <VTextField
              v-model="searchQuery"
              placeholder="Cari nama / no reg / no MR / petugas..."
              prepend-inner-icon="ri-search-line"
              variant="outlined" density="compact" hide-details clearable
            />
          </VCol>
          <VCol cols="6" sm="2">
            <VSelect
              v-model="filterBulan"
              :items="bulanList"
              label="Bulan"
              variant="outlined" density="compact" hide-details
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
        <div class="d-flex justify-space-between align-center gap-2 mt-2 flex-wrap">
          <VBtnToggle v-model="filterStatus" mandatory density="compact" rounded="lg">
            <VBtn value="All"      size="small" variant="outlined">Semua</VBtn>
            <VBtn value="Menunggu" size="small" variant="outlined" color="warning">Menunggu</VBtn>
            <VBtn value="Selesai"  size="small" variant="outlined" color="success">Selesai</VBtn>
          </VBtnToggle>
          <VBtn size="small" variant="text" color="secondary" prepend-icon="ri-refresh-line" @click="resetFilters">Reset</VBtn>
        </div>
      </VCardText>
    </VCard>

    <!-- Result count -->
    <div class="d-flex align-center gap-2 mb-4 flex-wrap">
      <VChip size="small" color="primary" variant="tonal">{{ filtered.length }} data</VChip>
      <span class="text-caption text-disabled">dari {{ records.length }} total</span>
      <VProgressCircular v-if="loading" size="16" width="2" indeterminate color="primary" />
      <VSpacer />
      <span v-if="lastSync" class="text-caption text-disabled">
        <VIcon icon="ri-refresh-line" size="12" class="me-1" />
        Sync: {{ lastSync.toLocaleTimeString('id-ID', { hour:'2-digit', minute:'2-digit', second:'2-digit' }) }}
      </span>
    </div>

    <!-- Cards grid -->
    <VRow v-if="!loading && filtered.length" dense>
      <VCol v-for="patient in filtered" :key="patient.no_mr" cols="12" sm="6" md="4" lg="3">
        <EdukasiPatientCard
          :patient="patient"
          :sesi-count="sesiPerMr[patient.no_mr] ?? 1"
          @view="openDetail"
          @edit="openEdit"
        />
      </VCol>
    </VRow>

    <!-- Loading skeleton -->
    <VRow v-else-if="loading" dense>
      <VCol v-for="n in 8" :key="n" cols="12" sm="6" md="4" lg="3">
        <VCard elevation="0" border rounded="xl" class="pa-4">
          <VSkeletonLoader type="list-item-avatar-two-line" />
        </VCard>
      </VCol>
    </VRow>

    <!-- Empty state -->
    <div v-else class="text-center py-16 text-medium-emphasis">
      <VIcon icon="ri-book-open-line" size="56" class="mb-3 opacity-40" />
      <p class="text-body-1 font-weight-medium mb-1">Tidak ada data</p>
      <p class="text-body-2 mb-4">Coba ubah filter atau klik Sync SIMRS untuk data terbaru</p>
      <VBtn variant="tonal" color="primary" prepend-icon="ri-refresh-line" rounded="lg" @click="resetFilters">
        Reset Filter
      </VBtn>
    </div>

    <!-- Detail dialog -->
    <EdukasiDetailDialog v-model="detailDialog" :patient="selectedPatient" :mode="dialogMode" @saved="onSaved" />
  </div>
</template>

<style scoped>
.stat-card { transition: box-shadow 0.2s, transform 0.15s; cursor: pointer; }
.stat-card:hover { box-shadow: 0 4px 16px rgba(var(--v-shadow-key-umbra-color), 0.1) !important; transform: translateY(-1px); }
</style>
