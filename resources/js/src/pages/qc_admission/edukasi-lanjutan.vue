<script setup>
import { useEdukasiLanjutanStore } from '@/stores/useEdukasiLanjutanStore'
import EdukasiDetailDialog         from '@/views/qc-admission/edukasi-lanjutan/EdukasiDetailDialog.vue'
import SummaryCards                from '@/components/SummaryCards.vue'

const store   = useEdukasiLanjutanStore()
const loading = ref(false)
const syncing = ref(false)
const snackbar = ref({ show: false, msg: '', color: 'success' })

// ── Detail dialog ─────────────────────────────────────────────────────────────
const showDetail   = ref(false)
const detailPatient = ref(null)
const dialogMode   = ref('view')

function todayStr() {
  const d = new Date(), p = n => String(n).padStart(2,'0')
  return `${d.getFullYear()}-${p(d.getMonth()+1)}-${p(d.getDate())}`
}

const search   = ref('')
const dateFrom = ref('')   // default kosong = tampilkan semua, user filter sendiri
const dateTo   = ref('')
const statusFilter = ref('All')

const records = computed(() => store.records ?? [])

// Dedupe per no_mr — tampilkan 1 card per pasien (entry terbaru)
const uniquePatients = computed(() => {
  const map = {}
  records.value.forEach(r => {
    if (!map[r.no_mr] || r.id > map[r.no_mr].id) map[r.no_mr] = r
  })
  return Object.values(map)
})

const sesiCount = computed(() => {
  const map = {}
  records.value.forEach(r => { map[r.no_mr] = (map[r.no_mr] ?? 0) + 1 })
  return map
})

const filtered = computed(() => {
  let d = uniquePatients.value
  // Status filter (client-side, sudah ada datanya)
  if (statusFilter.value !== 'All') d = d.filter(r => r.status === statusFilter.value)
  // Search filter (client-side)
  if (search.value.trim()) {
    const q = search.value.toLowerCase()
    d = d.filter(r =>
      r.nama_pasien?.toLowerCase().includes(q) ||
      r.no_mr?.toLowerCase().includes(q) ||
      r.no_reg?.toLowerCase().includes(q) ||
      r.petugas?.toLowerCase().includes(q)
    )
  }
  // Date filter sudah dilakukan server-side via load() — tidak perlu client filter
  return d
})

const stats = computed(() => ({
  total:    uniquePatients.value.length,
  menunggu: uniquePatients.value.filter(r => r.status === 'Menunggu').length,
  selesai:  uniquePatients.value.filter(r => r.status === 'Selesai').length,
  pct:      uniquePatients.value.length
    ? Math.round(uniquePatients.value.filter(r => r.status === 'Selesai').length / uniquePatients.value.length * 100) : 0,
}))

function openDetail(patient) { detailPatient.value = { ...patient }; dialogMode.value = 'view'; showDetail.value = true }
function openEdit(patient)   { detailPatient.value = { ...patient }; dialogMode.value = 'edit'; showDetail.value = true }

async function onSaved() { showDetail.value = false; await load() }

function toast(msg, color='success') { snackbar.value = { show: true, msg, color } }

function resetFilter() {
  search.value = ''
  statusFilter.value = 'All'
  dateFrom.value = ''
  dateTo.value = ''
  load()
}

async function syncRsus() {
  syncing.value = true
  try {
    await store.syncFromRsus()
    toast('Sync SIMRS selesai.')
    await load()
  } catch { toast('Sync gagal.', 'error') }
  finally { syncing.value = false }
}

async function load() {
  loading.value = true
  try {
    await store.fetchRecords({
      per_page: 200,
      search:    search.value || undefined,
      status:    statusFilter.value !== 'All' ? statusFilter.value : undefined,
      date_from: dateFrom.value || undefined,
      date_to:   dateTo.value   || undefined,
    })
  } catch(e) { console.error(e) }
  finally { loading.value = false }
}

// Watch date filter — re-fetch dari API saat tanggal berubah
watch([dateFrom, dateTo], () => load())

onMounted(load)
</script>

<template>
  <div>
    <!-- Header -->
    <div class="page-hero page-hero--edukasi">
      <div class="page-hero__content">
        <div class="page-hero__badge"><VIcon icon="ri-book-open-line" size="12" />Edukasi Lanjutan</div>
        <h1 class="page-hero__title">Edukasi Lanjutan</h1>
        <p class="page-hero__subtitle">Auto dari QC ≥ 2 jam · Klik pasien untuk tambah sesi</p>
      </div>
      <div class="page-hero__actions">
        <VBtn icon variant="text" color="white" size="small" :loading="loading" @click="load">
          <VIcon icon="ri-refresh-line" />
        </VBtn>
        <VBtn color="white" variant="elevated" rounded="pill" size="small" style="color:#065F46;font-weight:700"
          :loading="syncing" @click="syncRsus">
          <VIcon icon="ri-refresh-line" size="15" class="me-1" />Sync SIMRS
        </VBtn>
      </div>
      <VIcon icon="ri-book-open-line" class="page-hero__icon" />
    </div>

    <!-- Stats -->
    <SummaryCards
      v-model="statusFilter"
      :cards="[
        { value: stats.total,    label: 'Total Pasien',    color: 'primary', icon: 'ri-book-open-line',    filterValue: 'All' },
        { value: stats.menunggu, label: 'Menunggu Bed',    color: 'warning', icon: 'ri-time-line',         filterValue: 'Menunggu' },
        { value: stats.selesai,  label: 'Sudah Dapat Bed', color: 'success', icon: 'ri-check-double-line', filterValue: 'Selesai', pct: stats.pct },
      ]"
    />

    <!-- Filter -->
    <VCard elevation="0" border rounded="xl" class="mb-4">
      <VCardText class="pa-3">
        <VRow dense align="center">
          <VCol cols="12" sm="4">
            <VTextField v-model="search" label="Cari pasien..." prepend-inner-icon="ri-search-line"
              variant="outlined" density="compact" hide-details clearable rounded="lg" />
          </VCol>
          <VCol cols="6" sm="3">
            <VTextField v-model="dateFrom" label="Dari" type="date" variant="outlined" density="compact" hide-details rounded="lg" />
          </VCol>
          <VCol cols="6" sm="3">
            <VTextField v-model="dateTo" label="Sampai" type="date" variant="outlined" density="compact" hide-details rounded="lg" />
          </VCol>
          <VCol cols="auto">
            <VBtn size="small" variant="text" color="secondary" @click="resetFilter; load()">Reset</VBtn>
          </VCol>
        </VRow>
        <!-- Date filter with "Lihat Semua" option -->
        <div class="d-flex gap-2 mt-3 flex-wrap align-center">
          <VChip
            v-for="s in ['All','Menunggu','Selesai']" :key="s"
            :color="statusFilter === s ? (s === 'Menunggu' ? 'warning' : s === 'Selesai' ? 'success' : 'primary') : 'default'"
            :variant="statusFilter === s ? 'elevated' : 'outlined'"
            size="small" class="cursor-pointer"
            @click="statusFilter = s"
          >{{ s === 'All' ? 'Semua Status' : s }}</VChip>
          <VDivider vertical class="mx-1" style="height:20px" />
          <VChip
            color="secondary" variant="outlined" size="small"
            class="cursor-pointer"
            @click="dateFrom = ''; dateTo = ''; load()"
          >
            <VIcon icon="ri-calendar-line" size="11" class="me-1" />Semua Tanggal
          </VChip>
        </div>
      </VCardText>
    </VCard>

    <!-- Count -->
    <div class="d-flex align-center gap-3 mb-4 flex-wrap">
      <VChip size="small" color="primary" variant="tonal" rounded="pill">{{ filtered.length }} pasien</VChip>
      <span class="text-caption" style="color:var(--qc-text-2)">Klik untuk lihat riwayat & tambah sesi</span>
    </div>

    <!-- Loading -->
    <div v-if="loading" class="text-center py-12">
      <VProgressCircular indeterminate color="success" size="32" />
    </div>

    <!-- Empty -->
    <div v-else-if="!filtered.length" class="text-center py-16" style="color:var(--qc-text-2)">
      <VIcon icon="ri-book-open-line" size="52" class="mb-3 opacity-30" />
      <p class="text-body-1 font-weight-semibold mb-1">Tidak ada data</p>
      <p class="text-caption mb-4">Data masuk otomatis dari QC setelah ≥ 2 jam</p>
      <VBtn variant="tonal" color="success" rounded="lg" size="small" @click="syncRsus" :loading="syncing">
        Sync SIMRS
      </VBtn>
    </div>

    <!-- Cards grid -->
    <VRow v-else dense>
      <VCol
        v-for="patient in filtered"
        :key="patient.no_mr"
        cols="12" sm="6" md="4" lg="3"
      >
        <VCard
          elevation="0" border rounded="xl"
          class="edu-card cursor-pointer h-100"
          @click="openDetail(patient)"
        >
          <VCardText class="pa-4">
            <!-- Top row -->
            <div class="d-flex align-start gap-3 mb-3">
              <VAvatar color="success" variant="tonal" size="44" rounded="lg">
                <span style="font-size:15px;font-weight:700">{{ patient.nama_pasien?.charAt(0) ?? '?' }}</span>
              </VAvatar>
              <div class="flex-grow-1 min-width-0">
                <p class="font-weight-semibold mb-0 text-truncate" style="font-size:0.9rem;color:var(--qc-text)">
                  {{ patient.nama_pasien }}
                </p>
                <p class="text-caption mb-0" style="color:var(--qc-text-2)">{{ patient.no_mr }}</p>
              </div>
              <!-- Session count -->
              <VChip color="warning" variant="tonal" size="x-small" class="flex-shrink-0">
                {{ sesiCount[patient.no_mr] ?? 1 }} sesi
              </VChip>
            </div>

            <!-- Status + info -->
            <div class="d-flex align-center gap-2 flex-wrap mb-2">
              <VChip
                :color="patient.status === 'Selesai' ? 'success' : 'warning'"
                variant="tonal" size="x-small"
              >{{ patient.status }}</VChip>
              <span v-if="patient.jaminan" class="text-caption" style="color:var(--qc-text-2)">{{ patient.jaminan }}</span>
            </div>

            <!-- Footer -->
            <div class="d-flex align-center justify-space-between">
              <span class="text-caption" style="color:var(--qc-text-2)">
                <VIcon icon="ri-user-line" size="11" class="me-1" />{{ patient.petugas }}
              </span>
              <span class="text-caption" style="color:var(--qc-text-2)">{{ patient.tanggal }}</span>
            </div>
          </VCardText>
        </VCard>
      </VCol>
    </VRow>

    <!-- Detail Dialog -->
    <EdukasiDetailDialog
      v-model="showDetail"
      :patient="detailPatient"
      :mode="dialogMode"
      @saved="onSaved"
    />

    <VSnackbar v-model="snackbar.show" :color="snackbar.color" timeout="3000" location="bottom right" rounded="xl">
      {{ snackbar.msg }}
      <template #actions><VBtn variant="text" size="small" @click="snackbar.show=false">✕</VBtn></template>
    </VSnackbar>
  </div>
</template>

<style scoped>
.edu-card {
  transition: box-shadow 0.18s, transform 0.18s, border-color 0.15s;
}
.edu-card:hover {
  box-shadow: 0 6px 20px rgba(16,185,129,0.15) !important;
  transform: translateY(-2px);
  border-color: rgba(16,185,129,0.35) !important;
}
</style>
